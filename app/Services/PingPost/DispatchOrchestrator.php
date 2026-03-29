<?php

namespace App\Services\PingPost;

use App\Enums\DispatchStatus;
use App\Enums\PingResultStatus;
use App\Enums\WorkflowStrategy;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\PingResult;
use App\Models\Workflow;
use App\Models\WorkflowBuyer;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class DispatchOrchestrator
{
    public function __construct(
        private readonly EligibilityCheckerService $eligibility,
        private readonly CapCheckerService $caps,
        private readonly PingService $pinger,
        private readonly PostService $poster,
        private readonly PriceResolverService $priceResolver,
    ) {}

    /**
     * Main entry point: dispatch a lead through a workflow.
     */
    public function dispatch(Workflow $workflow, Lead $lead, string $fingerprint): LeadDispatch
    {
        $leadData = $lead->leadFieldResponses->pluck('value', 'field.name')->toArray();

        $dispatch = LeadDispatch::create([
            'workflow_id' => $workflow->id,
            'lead_id' => $lead->id,
            'fingerprint' => $fingerprint,
            'status' => DispatchStatus::RUNNING,
            'strategy_used' => $workflow->strategy->value,
            'started_at' => now(),
        ]);

        try {
            match ($workflow->strategy) {
                WorkflowStrategy::BEST_BID => $this->runBestBid($workflow, $dispatch, $leadData),
                WorkflowStrategy::WATERFALL => $this->runWaterfall($workflow, $dispatch, $leadData),
                WorkflowStrategy::COMBINED => $this->runCombined($workflow, $dispatch, $leadData),
            };
        } catch (Throwable $e) {
            $dispatch->markAsError($e->getMessage());
        }

        $dispatch->refresh();
        if (! $dispatch->status->isTerminal()) {
            $dispatch->markAsNotSold();
        }

        $dispatch->update([
            'total_duration_ms' => (int) round((now()->diffInMilliseconds($dispatch->started_at))),
        ]);

        return $dispatch->fresh();
    }

    // -------------------------------------------------------------------------
    // Strategies
    // -------------------------------------------------------------------------

    private function runBestBid(Workflow $workflow, LeadDispatch $dispatch, array $leadData): void
    {
        $buyers = $this->getEligibleBuyers($workflow, $dispatch, $leadData, 'primary');

        if ($buyers->isEmpty()) {
            $this->activateFallback($workflow, $dispatch, $leadData);

            return;
        }

        $pingResults = $this->pingAllParallel($buyers, $dispatch, $leadData);

        // Sort by bid_price DESC, filter out non-accepted
        $ranked = collect($pingResults)
            ->filter(fn (PingResult $r) => $r->status === PingResultStatus::ACCEPTED && $r->bid_price > 0)
            ->sortByDesc(fn (PingResult $r) => (float) $r->bid_price)
            ->values();

        $retries = 0;
        $sold = false;

        foreach ($ranked as $pingResult) {
            if ($retries >= $workflow->cascade_max_retries) {
                break;
            }

            $integration = $buyers->firstWhere('integration_id', $pingResult->integration_id)?->integration;

            if (! $integration) {
                continue;
            }

            $config = $integration->buyerConfig;
            $offeredPrice = (float) $pingResult->bid_price;

            $postResult = $this->poster->post($integration, $config, $dispatch, $leadData, $pingResult, $offeredPrice);

            if ($postResult->status->isSold() || $postResult->status === \App\Enums\PostResultStatus::PENDING_POSTBACK) {
                if ($postResult->status->isSold()) {
                    $dispatch->markAsSold($integration, $offeredPrice);
                }
                $sold = true;
                break;
            }

            if (! $workflow->cascade_on_post_rejection) {
                break;
            }

            $retries++;
        }

        if (! $sold) {
            $this->activateFallback($workflow, $dispatch, $leadData);
        }
    }

    private function runWaterfall(Workflow $workflow, LeadDispatch $dispatch, array $leadData, string $group = 'primary'): bool
    {
        $buyers = $this->getEligibleBuyers($workflow, $dispatch, $leadData, $group);

        foreach ($buyers as $wfBuyer) {
            $integration = $wfBuyer->integration;
            $config = $integration->buyerConfig;

            if (! $config) {
                continue;
            }

            // Post-only buyer: skip ping phase
            $pingResult = null;
            $offeredPrice = (float) ($config->fixed_price ?? 0);

            if ($integration->type === 'ping-post') {
                $pingResult = $this->pinger->ping($integration, $config, $dispatch, $leadData);

                if ($pingResult->status === PingResultStatus::ACCEPTED) {
                    $offeredPrice = $this->priceResolver->resolvePrice($config, (float) $pingResult->bid_price) ?? 0;
                } elseif ($pingResult->status === PingResultStatus::REJECTED && $workflow->advance_on_rejection) {
                    continue;
                } elseif ($pingResult->status === PingResultStatus::TIMEOUT && $workflow->advance_on_timeout) {
                    continue;
                } elseif ($pingResult->status === PingResultStatus::ERROR && $workflow->advance_on_error) {
                    continue;
                } else {
                    break; // don't advance on this status
                }
            }

            $postResult = $this->poster->post($integration, $config, $dispatch, $leadData, $pingResult, $offeredPrice);

            if ($postResult->status->isSold()) {
                $dispatch->markAsSold($integration, $offeredPrice);

                return true;
            }

            if ($postResult->status === \App\Enums\PostResultStatus::PENDING_POSTBACK) {
                return true; // async, consider handled
            }

            if ($postResult->status === \App\Enums\PostResultStatus::REJECTED && $workflow->advance_on_rejection) {
                continue;
            }

            if ($postResult->status === \App\Enums\PostResultStatus::TIMEOUT && $workflow->advance_on_timeout) {
                continue;
            }

            if ($postResult->status === \App\Enums\PostResultStatus::ERROR && $workflow->advance_on_error) {
                continue;
            }

            break;
        }

        return false;
    }

    private function runCombined(Workflow $workflow, LeadDispatch $dispatch, array $leadData): void
    {
        // Best Bid on primary group
        $primaryBuyers = $this->getEligibleBuyers($workflow, $dispatch, $leadData, 'primary');

        if ($primaryBuyers->isNotEmpty()) {
            $pingResults = $this->pingAllParallel($primaryBuyers, $dispatch, $leadData);

            $ranked = collect($pingResults)
                ->filter(fn (PingResult $r) => $r->status === PingResultStatus::ACCEPTED && $r->bid_price > 0)
                ->sortByDesc(fn (PingResult $r) => (float) $r->bid_price)
                ->values();

            foreach ($ranked->take($workflow->cascade_max_retries) as $pingResult) {
                $integration = $primaryBuyers->firstWhere('integration_id', $pingResult->integration_id)?->integration;

                if (! $integration) {
                    continue;
                }

                $config = $integration->buyerConfig;
                $offeredPrice = (float) $pingResult->bid_price;
                $postResult = $this->poster->post($integration, $config, $dispatch, $leadData, $pingResult, $offeredPrice);

                if ($postResult->status->isSold()) {
                    $dispatch->markAsSold($integration, $offeredPrice);

                    return;
                }

                if ($postResult->status === \App\Enums\PostResultStatus::PENDING_POSTBACK) {
                    return;
                }

                if (! $workflow->cascade_on_post_rejection) {
                    break;
                }
            }
        }

        // Waterfall on secondary group
        $dispatch->update(['fallback_activated' => true]);
        $sold = $this->runWaterfall($workflow, $dispatch, $leadData, 'secondary');

        if (! $sold) {
            $dispatch->markAsNotSold();
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Execute pings in parallel using Http::pool(), same pattern as MixService.
     *
     * @param  Collection<int, WorkflowBuyer>  $buyers
     * @return array<int, PingResult> Keyed by integration_id
     */
    private function pingAllParallel(Collection $buyers, LeadDispatch $dispatch, array $leadData): array
    {
        $requests = [];
        $pingResults = [];

        foreach ($buyers as $wfBuyer) {
            $integration = $wfBuyer->integration;
            $config = $integration->buyerConfig;

            if (! $config || $integration->type === 'post-only') {
                continue;
            }

            $pingEnv = $integration->environments
                ->where('env_type', 'ping')
                ->where('environment', 'production')
                ->first();

            if (! $pingEnv || ! $pingEnv->url) {
                continue;
            }

            $mappingConfig = $integration->request_mapping_config ?? [];
            $replacements = \App\Services\PayloadProcessorService::generateReplacements($leadData, $mappingConfig);
            $finals = $replacements['finalReplacements'] ?? [];

            $processor = new \App\Services\PayloadProcessorService;
            $url = $processor->processUrl($pingEnv->url, $finals);
            $payloadString = $processor->process($pingEnv->request_body ?? '{}', $finals);
            $payload = json_decode($payloadString, true) ?? [];
            $headersString = $processor->process($pingEnv->request_headers ?? '{}', $finals);
            $headers = json_decode($headersString, true) ?? [];
            $method = strtolower($pingEnv->method ?? 'post');

            $requests[$integration->id] = compact('integration', 'config', 'pingEnv', 'url', 'payload', 'headers', 'method', 'dispatch');
        }

        if (empty($requests)) {
            return [];
        }

        $responses = Http::pool(function (Pool $pool) use ($requests): void {
            foreach ($requests as $integrationId => $data) {
                $pool->as($integrationId)
                    ->withHeaders($data['headers'])
                    ->timeout($data['config']->ping_timeout_ms / 1000)
                    ->{$data['method']}($data['url'], $data['payload']);
            }
        });

        foreach ($requests as $integrationId => $data) {
            $response = $responses[$integrationId] ?? null;
            $integration = $data['integration'];
            $pingEnv = $data['pingEnv'];

            $idempotencyKey = LeadDispatch::generateIdempotencyKey($dispatch->workflow_id, $integrationId, $dispatch->fingerprint);

            if ($response instanceof \Illuminate\Http\Client\Response) {
                $config = $pingEnv->config;
                $bidPrice = $this->extractBidFromResponse($response, $config);
                $accepted = $this->isAcceptedResponse($response, $config);

                $pingResults[$integrationId] = PingResult::create([
                    'lead_dispatch_id' => $dispatch->id,
                    'integration_id' => $integrationId,
                    'idempotency_key' => $idempotencyKey,
                    'status' => $accepted ? PingResultStatus::ACCEPTED : PingResultStatus::REJECTED,
                    'bid_price' => $bidPrice,
                    'http_status_code' => $response->status(),
                    'request_url' => $data['url'],
                    'request_payload' => $data['payload'],
                    'request_headers' => $data['headers'],
                    'response_body' => $response->json() ?? ['raw' => $response->body()],
                ]);
            } else {
                $isTimeout = $response instanceof Throwable
                    && (str_contains($response->getMessage(), 'timed out') || str_contains($response->getMessage(), 'timeout'));

                $pingResults[$integrationId] = PingResult::create([
                    'lead_dispatch_id' => $dispatch->id,
                    'integration_id' => $integrationId,
                    'idempotency_key' => $idempotencyKey,
                    'status' => $isTimeout ? PingResultStatus::TIMEOUT : PingResultStatus::ERROR,
                    'request_url' => $data['url'],
                    'request_payload' => $data['payload'],
                    'request_headers' => $data['headers'],
                    'response_body' => ['error' => $response instanceof Throwable ? $response->getMessage() : 'unknown'],
                ]);
            }
        }

        return $pingResults;
    }

    private function activateFallback(Workflow $workflow, LeadDispatch $dispatch, array $leadData): void
    {
        $fallbackBuyers = $workflow->workflowBuyers()
            ->where('is_fallback', true)
            ->where('is_active', true)
            ->with('integration.buyerConfig', 'integration.environments')
            ->get();

        if ($fallbackBuyers->isEmpty()) {
            $dispatch->markAsNotSold();

            return;
        }

        $dispatch->update(['fallback_activated' => true]);
        $sold = false;

        foreach ($fallbackBuyers as $wfBuyer) {
            $integration = $wfBuyer->integration;
            $config = $integration->buyerConfig;

            if (! $config) {
                continue;
            }

            $offeredPrice = (float) ($config->fixed_price ?? 0);
            $postResult = $this->poster->post($integration, $config, $dispatch, $leadData, null, $offeredPrice);

            if ($postResult->status->isSold()) {
                $dispatch->markAsSold($integration, $offeredPrice);
                $sold = true;
                break;
            }
        }

        if (! $sold) {
            $dispatch->markAsNotSold();
        }
    }

    /**
     * Get eligible, non-cap-exceeded, non-duplicate active buyers for a group.
     *
     * @return Collection<int, WorkflowBuyer>
     */
    private function getEligibleBuyers(Workflow $workflow, LeadDispatch $dispatch, array $leadData, string $group): Collection
    {
        return $workflow->workflowBuyers()
            ->where('buyer_group', $group)
            ->where('is_active', true)
            ->where('is_fallback', false)
            ->with(['integration', 'integration.buyerConfig', 'integration.environments', 'integration.eligibilityRules', 'integration.capRules'])
            ->get()
            ->filter(function (WorkflowBuyer $wfBuyer) use ($dispatch, $leadData): bool {
                $integration = $wfBuyer->integration;

                if (! $integration || ! $integration->is_active) {
                    return false;
                }

                if ($this->isDuplicate($dispatch, $integration)) {
                    return false;
                }

                if (! $this->eligibility->isEligible($integration, $leadData)) {
                    return false;
                }

                if ($this->caps->isCapExceeded($integration)) {
                    return false;
                }

                return true;
            });
    }

    private function isDuplicate(LeadDispatch $dispatch, Integration $integration): bool
    {
        $key = LeadDispatch::generateIdempotencyKey($dispatch->workflow_id, $integration->id, $dispatch->fingerprint);

        return PingResult::where('idempotency_key', $key)->exists();
    }

    private function extractBidFromResponse(\Illuminate\Http\Client\Response $response, ?\App\Models\PingResponseConfig $config): ?float
    {
        $path = $config?->bid_price_path;

        if (! $path) {
            return null;
        }

        $value = \Illuminate\Support\Arr::get($response->json() ?? [], $path);

        return is_numeric($value) ? (float) $value : null;
    }

    private function isAcceptedResponse(\Illuminate\Http\Client\Response $response, ?\App\Models\PingResponseConfig $config): bool
    {
        $acceptedPath = $config?->accepted_path;
        $acceptedValue = $config?->accepted_value;

        if (! $acceptedPath) {
            return $response->successful();
        }

        $actual = \Illuminate\Support\Arr::get($response->json() ?? [], $acceptedPath);

        return (string) $actual === (string) $acceptedValue;
    }
}
