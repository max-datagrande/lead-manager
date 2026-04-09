<?php

namespace App\Services\PingPost;

use App\Enums\DispatchStatus;
use App\Enums\PingResultStatus;
use App\Enums\PriceSource;
use App\Enums\WorkflowStrategy;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Jobs\PingPost\FlushBuyerEventsJob;
use App\Jobs\PingPost\SendWorkflowAlertJob;
use App\Models\PingResult;
use App\Models\TrafficLog;
use App\Models\Workflow;
use App\Models\WorkflowBuyer;
use Illuminate\Http\Client\Pool;
use Maxidev\Logger\TailLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

class DispatchOrchestrator
{
  /** @var array<int, array{lead_dispatch_id: int, integration_id: int, event: string, reason: string, detail: ?string}> */
  private array $buyerEventsBuffer = [];

  public function __construct(
    private readonly EligibilityCheckerService $eligibility,
    private readonly CapCheckerService $caps,
    private readonly PingService $pinger,
    private readonly PostService $poster,
    private readonly PriceResolverService $priceResolver,
    private readonly DispatchTimelineService $timeline,
  ) {}

  /**
   * Main entry point: dispatch a lead through a workflow.
   */
  /**
   * @param  array<string, mixed>  $extra  Optional attributes merged into the LeadDispatch create (e.g. attempt, parent_dispatch_id).
   */
  public function dispatch(Workflow $workflow, Lead $lead, string $fingerprint, array $extra = []): LeadDispatch
  {
    $leadData = $lead->leadFieldResponses->pluck('value', 'field.name')->toArray();
    $leadSnapshot = $lead->leadFieldResponses->pluck('value', 'field_id')->toArray();
    TailLogger::saveLog('Orchestrator START', 'dispatch/debug', 'info', [
      'workflow_id' => $workflow->id,
      'strategy' => $workflow->strategy?->value,
      'strategy_type' => gettype($workflow->strategy),
      'lead_id' => $lead->id,
      'fingerprint' => $fingerprint,
      'leadData' => $leadData,
    ]);

    $utmSource = TrafficLog::where('fingerprint', $fingerprint)->latest('visit_date')->value('utm_source');

    $dispatch = LeadDispatch::create(
      array_merge(
        [
          'workflow_id' => $workflow->id,
          'lead_id' => $lead->id,
          'fingerprint' => $fingerprint,
          'lead_snapshot' => $leadSnapshot,
          'utm_source' => $utmSource,
          'status' => DispatchStatus::RUNNING,
          'strategy_used' => $workflow->strategy->value,
          'started_at' => now(),
        ],
        $extra, //'attempt','parent_dispatch_id'
      ),
    );

    $this->timeline->bind($fingerprint, $dispatch->id);
    $this->pinger->setTimeline($this->timeline);
    $this->poster->setTimeline($this->timeline);
    $this->timeline->log(DispatchTimelineService::DISPATCH_STARTED, "Workflow '{$workflow->name}' started ({$workflow->strategy->value})", [
      'workflow_id' => $workflow->id,
      'strategy' => $workflow->strategy->value,
      'lead_id' => $lead->id,
    ]);

    try {
      TailLogger::saveLog('Running strategy: ' . ($workflow->strategy?->value ?? 'NULL'), 'dispatch/debug');
      match ($workflow->strategy) {
        WorkflowStrategy::BEST_BID => $this->runBestBid($workflow, $dispatch, $leadData),
        WorkflowStrategy::WATERFALL => $this->runWaterfall($workflow, $dispatch, $leadData),
        WorkflowStrategy::COMBINED => $this->runCombined($workflow, $dispatch, $leadData),
      };
      TailLogger::saveLog('Strategy completed OK', 'dispatch/debug');
      $this->timeline->log(DispatchTimelineService::DISPATCH_COMPLETED, 'Strategy completed');
    } catch (Throwable $e) {
      TailLogger::saveLog('Strategy FAILED', 'dispatch/debug', 'error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile() . ':' . $e->getLine(),
        'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10),
      ]);
      $dispatch->markAsError($e->getMessage());
      $this->timeline->log(DispatchTimelineService::DISPATCH_ERROR, "Strategy failed: {$e->getMessage()}");
      SendWorkflowAlertJob::dispatch($workflow->id, "Workflow '{$workflow->name}' strategy failed: {$e->getMessage()}", [
        'title' => 'Workflow Dispatch Failed',
        'fields' => [
          'Workflow' => "{$workflow->name} (#{$workflow->id})",
          'Dispatch' => "#{$dispatch->id} — {$dispatch->dispatch_uuid}",
          'Strategy' => $dispatch->strategy_used ?? $workflow->strategy->value,
          'Error' => $e->getMessage(),
          'File' => "{$e->getFile()}:{$e->getLine()}",
        ],
      ]);
    } finally {
      $dispatch->refresh();
      if (!$dispatch->status->isTerminal()) {
        $dispatch->markAsNotSold();
        $this->timeline->log(DispatchTimelineService::OUTCOME_NOT_SOLD, 'No buyer accepted the lead');
      }
      $this->timeline->flush();
      $this->flushBuyerEvents();
    }

    $dispatch->update([
      'total_duration_ms' => (int) round($dispatch->started_at->diffInMilliseconds(now(), true)),
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
      ->filter(fn(PingResult $r) => $r->status === PingResultStatus::ACCEPTED && $r->bid_price > 0)
      ->sortByDesc(fn(PingResult $r) => (float) $r->bid_price)
      ->values();

    $retries = 0;
    $sold = false;

    foreach ($ranked as $pingResult) {
      if ($retries >= $workflow->cascade_max_retries) {
        $this->timeline->log(DispatchTimelineService::CASCADE_BREAK, "Max retries ({$workflow->cascade_max_retries}) reached");
        break;
      }

      $integration = $buyers->firstWhere('integration_id', $pingResult->integration_id)?->integration;

      if (!$integration) {
        continue;
      }

      $config = $integration->buyerConfig;
      $offeredPrice = $this->priceResolver->resolvePrice($config, (float) $pingResult->bid_price);

      if ($config->price_source === PriceSource::CONDITIONAL) {
        $offeredPrice = $this->priceResolver->resolveConditionalPrice($config, $leadData);
      }

      $this->timeline->log(
        DispatchTimelineService::PRICE_RESOLVED,
        "Price for {$integration->name}: \${$offeredPrice} (bid: \${$pingResult->bid_price})",
        [
          'integration_id' => $integration->id,
          'offered_price' => $offeredPrice,
          'bid_price' => $pingResult->bid_price,
          'price_source' => $config->price_source->value,
        ],
      );

      if ($config->price_source !== PriceSource::POSTBACK && ($offeredPrice === null || $offeredPrice <= 0) && !$config->sell_on_zero_price) {
        $this->timeline->log(DispatchTimelineService::PRICE_SKIPPED, "Skipping {$integration->name}: price below threshold", [
          'integration_id' => $integration->id,
        ]);
        $this->bufferBuyerEvent($dispatch->id, $integration->id, 'skipped', 'price_below_threshold', "price={$offeredPrice}");
        continue;
      }

      $offeredPrice = $offeredPrice ?? 0;

      $postResult = $this->poster->post($integration, $config, $dispatch, $leadData, $pingResult, $offeredPrice);

      $this->timeline->log(DispatchTimelineService::POST_RESULT, "Post to {$integration->name}: {$postResult->status->value}", [
        'post_result_id' => $postResult->id,
        'integration_id' => $integration->id,
        'status' => $postResult->status->value,
        'price_offered' => $offeredPrice,
      ]);

      if ($postResult->status->isSold() || $postResult->status === \App\Enums\PostResultStatus::PENDING_POSTBACK) {
        if ($postResult->status->isSold()) {
          $this->timeline->log(DispatchTimelineService::OUTCOME_SOLD, "Sold to {$integration->name} at \${$offeredPrice}", [
            'integration_id' => $integration->id,
            'price' => $offeredPrice,
          ]);
          $dispatch->markAsSold($integration, $offeredPrice);
        } else {
          $this->timeline->log(DispatchTimelineService::OUTCOME_PENDING_POSTBACK, "Pending postback from {$integration->name}", [
            'integration_id' => $integration->id,
            'post_result_id' => $postResult->id,
          ]);
        }
        $sold = true;
        break;
      }

      if (!$workflow->cascade_on_post_rejection) {
        $this->timeline->log(DispatchTimelineService::CASCADE_BREAK, "Cascade disabled, stopping at {$integration->name}");
        break;
      }

      $this->timeline->log(DispatchTimelineService::CASCADE_ADVANCE, "Advancing past {$integration->name} (post {$postResult->status->value})", [
        'integration_id' => $integration->id,
        'retry' => $retries + 1,
      ]);
      $retries++;
    }

    if (!$sold) {
      $this->activateFallback($workflow, $dispatch, $leadData);
    }
  }

  private function runWaterfall(Workflow $workflow, LeadDispatch $dispatch, array $leadData, string $group = 'primary'): bool
  {
    $buyers = $this->getEligibleBuyers($workflow, $dispatch, $leadData, $group);

    foreach ($buyers as $wfBuyer) {
      $integration = $wfBuyer->integration;
      $config = $integration->buyerConfig;

      if (!$config) {
        $this->timeline->log(DispatchTimelineService::BUYER_FILTERED, "Buyer {$integration->name} skipped: no buyer config", [
          'integration_id' => $integration->id,
          'reason' => 'no_config',
        ]);
        $this->bufferBuyerEvent($dispatch->id, $integration->id, 'skipped', 'no_config');
        continue;
      }

      // Post-only buyer: resolve price through resolver pattern
      $pingResult = null;
      $offeredPrice = $this->priceResolver->resolvePrice($config, 0);

      if ($config->price_source === PriceSource::CONDITIONAL) {
        $offeredPrice = $this->priceResolver->resolveConditionalPrice($config, $leadData);
      }

      if ($integration->type === 'ping-post') {
        $pingResult = $this->pinger->ping($integration, $config, $dispatch, $leadData);

        $this->timeline->log(
          DispatchTimelineService::PING_RESULT,
          "Ping {$integration->name}: {$pingResult->status->value}" . ($pingResult->bid_price ? " at \${$pingResult->bid_price}" : ''),
          [
            'ping_result_id' => $pingResult->id,
            'integration_id' => $integration->id,
            'status' => $pingResult->status->value,
            'bid_price' => $pingResult->bid_price,
          ],
        );

        if ($pingResult->status === PingResultStatus::ACCEPTED) {
          $offeredPrice = $this->priceResolver->resolvePrice($config, (float) $pingResult->bid_price);

          if ($config->price_source === PriceSource::CONDITIONAL) {
            $offeredPrice = $this->priceResolver->resolveConditionalPrice($config, $leadData);
          }
        } elseif ($pingResult->status === PingResultStatus::REJECTED && $workflow->advance_on_rejection) {
          $this->timeline->log(DispatchTimelineService::CASCADE_ADVANCE, "Advancing past {$integration->name} (ping rejected)", [
            'integration_id' => $integration->id,
          ]);
          continue;
        } elseif ($pingResult->status === PingResultStatus::TIMEOUT && $workflow->advance_on_timeout) {
          $this->timeline->log(DispatchTimelineService::CASCADE_ADVANCE, "Advancing past {$integration->name} (ping timeout)", [
            'integration_id' => $integration->id,
          ]);
          continue;
        } elseif ($pingResult->status === PingResultStatus::ERROR && $workflow->advance_on_error) {
          $this->timeline->log(DispatchTimelineService::CASCADE_ADVANCE, "Advancing past {$integration->name} (ping error)", [
            'integration_id' => $integration->id,
          ]);
          continue;
        } else {
          $this->timeline->log(
            DispatchTimelineService::CASCADE_BREAK,
            "Stopping cascade at {$integration->name} (ping {$pingResult->status->value})",
            [
              'integration_id' => $integration->id,
              'status' => $pingResult->status->value,
            ],
          );
          break;
        }
      }

      $this->timeline->log(
        DispatchTimelineService::PRICE_RESOLVED,
        "Price for {$integration->name}: \${$offeredPrice} (source: {$config->price_source->value})",
        [
          'integration_id' => $integration->id,
          'offered_price' => $offeredPrice,
          'price_source' => $config->price_source->value,
        ],
      );

      if ($config->price_source !== PriceSource::POSTBACK && ($offeredPrice === null || $offeredPrice <= 0) && !$config->sell_on_zero_price) {
        $this->timeline->log(DispatchTimelineService::PRICE_SKIPPED, "Skipping {$integration->name}: price \${$offeredPrice} below threshold", [
          'integration_id' => $integration->id,
          'offered_price' => $offeredPrice,
        ]);
        $this->bufferBuyerEvent($dispatch->id, $integration->id, 'skipped', 'price_below_threshold', "price={$offeredPrice}");
        continue;
      }

      $offeredPrice = $offeredPrice ?? 0;

      $postResult = $this->poster->post($integration, $config, $dispatch, $leadData, $pingResult, $offeredPrice);

      $this->timeline->log(DispatchTimelineService::POST_RESULT, "Post to {$integration->name}: {$postResult->status->value}", [
        'post_result_id' => $postResult->id,
        'integration_id' => $integration->id,
        'status' => $postResult->status->value,
        'price_offered' => $offeredPrice,
      ]);

      if ($postResult->status->isSold()) {
        $this->timeline->log(DispatchTimelineService::OUTCOME_SOLD, "Sold to {$integration->name} at \${$offeredPrice}", [
          'integration_id' => $integration->id,
          'price' => $offeredPrice,
        ]);
        $dispatch->markAsSold($integration, $offeredPrice);

        return true;
      }

      if ($postResult->status === \App\Enums\PostResultStatus::PENDING_POSTBACK) {
        $this->timeline->log(DispatchTimelineService::OUTCOME_PENDING_POSTBACK, "Pending postback from {$integration->name}", [
          'integration_id' => $integration->id,
          'post_result_id' => $postResult->id,
        ]);
        return true;
      }

      if ($postResult->status === \App\Enums\PostResultStatus::REJECTED && $workflow->advance_on_rejection) {
        $this->timeline->log(DispatchTimelineService::CASCADE_ADVANCE, "Advancing past {$integration->name} (post rejected)", [
          'integration_id' => $integration->id,
        ]);
        continue;
      }

      if ($postResult->status === \App\Enums\PostResultStatus::TIMEOUT && $workflow->advance_on_timeout) {
        $this->timeline->log(DispatchTimelineService::CASCADE_ADVANCE, "Advancing past {$integration->name} (post timeout)", [
          'integration_id' => $integration->id,
        ]);
        continue;
      }

      if ($postResult->status === \App\Enums\PostResultStatus::ERROR && $workflow->advance_on_error) {
        $this->timeline->log(DispatchTimelineService::CASCADE_ADVANCE, "Advancing past {$integration->name} (post error)", [
          'integration_id' => $integration->id,
        ]);
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
        ->filter(fn(PingResult $r) => $r->status === PingResultStatus::ACCEPTED && $r->bid_price > 0)
        ->sortByDesc(fn(PingResult $r) => (float) $r->bid_price)
        ->values();

      foreach ($ranked->take($workflow->cascade_max_retries) as $pingResult) {
        $integration = $primaryBuyers->firstWhere('integration_id', $pingResult->integration_id)?->integration;

        if (!$integration) {
          continue;
        }

        $config = $integration->buyerConfig;
        $offeredPrice = $this->priceResolver->resolvePrice($config, (float) $pingResult->bid_price);

        if ($config->price_source === PriceSource::CONDITIONAL) {
          $offeredPrice = $this->priceResolver->resolveConditionalPrice($config, $leadData);
        }

        if ($config->price_source !== PriceSource::POSTBACK && ($offeredPrice === null || $offeredPrice <= 0) && !$config->sell_on_zero_price) {
          continue;
        }

        $offeredPrice = $offeredPrice ?? 0;
        $postResult = $this->poster->post($integration, $config, $dispatch, $leadData, $pingResult, $offeredPrice);

        if ($postResult->status->isSold()) {
          $dispatch->markAsSold($integration, $offeredPrice);

          return;
        }

        if ($postResult->status === \App\Enums\PostResultStatus::PENDING_POSTBACK) {
          return;
        }

        if (!$workflow->cascade_on_post_rejection) {
          break;
        }
      }
    }

    // Waterfall on secondary group
    $dispatch->update(['fallback_activated' => true]);
    $sold = $this->runWaterfall($workflow, $dispatch, $leadData, 'secondary');

    if (!$sold) {
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

      if (!$config || $integration->type === 'post-only') {
        continue;
      }

      $pingEnv = $integration->environments->where('env_type', 'ping')->where('environment', 'production')->first();

      if (!$pingEnv || !$pingEnv->url) {
        continue;
      }

      $processor = new \App\Services\PayloadProcessorService();
      $replacements = $processor->buildReplacements($integration, $pingEnv, $leadData);
      $url = $processor->applyReplacements($pingEnv->url ?? '', $replacements);
      $payload = json_decode($processor->applyReplacements($pingEnv->request_body ?? '{}', $replacements), true) ?? [];
      $payload = $processor->applyTwigTransformer($integration, $payload);
      $headers = json_decode($processor->applyReplacements($pingEnv->request_headers ?? '{}', $replacements), true) ?? [];
      $method = strtolower($pingEnv->method ?? 'post');

      $requests[$integration->id] = compact('integration', 'config', 'pingEnv', 'url', 'payload', 'headers', 'method', 'dispatch');
    }

    if (empty($requests)) {
      return [];
    }

    $responses = Http::pool(function (Pool $pool) use ($requests): void {
      foreach ($requests as $integrationId => $data) {
        $pool
          ->as($integrationId)
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
        $config = $pingEnv->response_config;
        $bidPrice = $this->extractBidFromResponse($response, $config);
        $accepted = $this->isAcceptedResponse($response, $config);

        $pingResult = PingResult::create([
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

        $pingResults[$integrationId] = $pingResult;

        $this->timeline->log(
          DispatchTimelineService::PING_RESULT,
          "Ping {$integration->name}: {$pingResult->status->value}" . ($bidPrice ? " at \${$bidPrice}" : ''),
          [
            'ping_result_id' => $pingResult->id,
            'integration_id' => $integrationId,
            'status' => $pingResult->status->value,
            'bid_price' => $bidPrice,
          ],
        );
      } else {
        $isTimeout =
          $response instanceof Throwable && (str_contains($response->getMessage(), 'timed out') || str_contains($response->getMessage(), 'timeout'));

        $pingResult = PingResult::create([
          'lead_dispatch_id' => $dispatch->id,
          'integration_id' => $integrationId,
          'idempotency_key' => $idempotencyKey,
          'status' => $isTimeout ? PingResultStatus::TIMEOUT : PingResultStatus::ERROR,
          'request_url' => $data['url'],
          'request_payload' => $data['payload'],
          'request_headers' => $data['headers'],
          'response_body' => ['error' => $response instanceof Throwable ? $response->getMessage() : 'unknown'],
        ]);

        $pingResults[$integrationId] = $pingResult;

        $this->timeline->log(DispatchTimelineService::PING_RESULT, "Ping {$integration->name}: {$pingResult->status->value}", [
          'ping_result_id' => $pingResult->id,
          'integration_id' => $integrationId,
          'status' => $pingResult->status->value,
        ]);
      }
    }

    $accepted = collect($pingResults)->filter(fn(PingResult $r) => $r->status === PingResultStatus::ACCEPTED)->count();
    $this->timeline->log(DispatchTimelineService::PING_PARALLEL_COMPLETE, "{$accepted} accepted out of " . count($pingResults) . ' pings', [
      'total' => count($pingResults),
      'accepted' => $accepted,
    ]);

    return $pingResults;
  }

  private function activateFallback(Workflow $workflow, LeadDispatch $dispatch, array $leadData): void
  {
    $fallbackBuyers = $workflow
      ->workflowBuyers()
      ->where('is_fallback', true)
      ->where('is_active', true)
      ->with('integration.buyerConfig', 'integration.environments.fieldHashes', 'integration.tokenMappings.field')
      ->get();

    if ($fallbackBuyers->isEmpty()) {
      $this->timeline->log(DispatchTimelineService::FALLBACK_NO_BUYERS, 'No fallback buyers available');
      $dispatch->markAsNotSold();

      return;
    }

    $this->timeline->log(DispatchTimelineService::FALLBACK_ACTIVATED, "Fallback activated: {$fallbackBuyers->count()} buyers available", [
      'fallback_buyer_count' => $fallbackBuyers->count(),
    ]);

    $dispatch->update(['fallback_activated' => true]);
    $sold = false;

    foreach ($fallbackBuyers as $wfBuyer) {
      $integration = $wfBuyer->integration;
      $config = $integration->buyerConfig;

      if (!$config) {
        continue;
      }

      $offeredPrice = $this->priceResolver->resolvePrice($config, 0);

      if ($config->price_source === PriceSource::CONDITIONAL) {
        $offeredPrice = $this->priceResolver->resolveConditionalPrice($config, $leadData);
      }

      if ($config->price_source !== PriceSource::POSTBACK && ($offeredPrice === null || $offeredPrice <= 0) && !$config->sell_on_zero_price) {
        $this->timeline->log(DispatchTimelineService::PRICE_SKIPPED, "Fallback {$integration->name}: price below threshold", [
          'integration_id' => $integration->id,
        ]);
        continue;
      }

      $offeredPrice = $offeredPrice ?? 0;
      $postResult = $this->poster->post($integration, $config, $dispatch, $leadData, null, $offeredPrice);

      $this->timeline->log(DispatchTimelineService::POST_RESULT, "Fallback post to {$integration->name}: {$postResult->status->value}", [
        'post_result_id' => $postResult->id,
        'integration_id' => $integration->id,
        'status' => $postResult->status->value,
        'price_offered' => $offeredPrice,
      ]);

      if ($postResult->status->isSold()) {
        $this->timeline->log(DispatchTimelineService::OUTCOME_SOLD, "Sold via fallback to {$integration->name} at \${$offeredPrice}", [
          'integration_id' => $integration->id,
          'price' => $offeredPrice,
        ]);
        $dispatch->markAsSold($integration, $offeredPrice);
        $sold = true;
        break;
      }
    }

    if (!$sold) {
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
    $allBuyers = $workflow
      ->workflowBuyers()
      ->where('buyer_group', $group)
      ->where('is_active', true)
      ->where('is_fallback', false)
      ->with([
        'integration',
        'integration.buyerConfig',
        'integration.environments.fieldHashes',
        'integration.tokenMappings.field',
        'integration.eligibilityRules',
        'integration.capRules',
      ])
      ->get();

    $eligible = $allBuyers->filter(function (WorkflowBuyer $wfBuyer) use ($dispatch, $leadData): bool {
      $integration = $wfBuyer->integration;

      if (!$integration || !$integration->is_active) {
        $this->timeline->log(DispatchTimelineService::BUYER_FILTERED, "Buyer {$integration?->name} filtered: inactive", [
          'integration_id' => $integration?->id,
          'reason' => 'inactive',
        ]);
        if ($integration) {
          $this->bufferBuyerEvent($dispatch->id, $integration->id, 'filtered', 'inactive');
        }
        return false;
      }

      if ($this->isDuplicate($dispatch, $integration)) {
        $this->timeline->log(DispatchTimelineService::BUYER_FILTERED, "Buyer {$integration->name} filtered: duplicate", [
          'integration_id' => $integration->id,
          'reason' => 'duplicate',
        ]);
        $this->bufferBuyerEvent($dispatch->id, $integration->id, 'filtered', 'duplicate');
        return false;
      }

      if (!$this->eligibility->isEligible($integration, $leadData)) {
        $skipReason = $this->eligibility->getSkipReason($integration, $leadData);
        $this->timeline->log(DispatchTimelineService::BUYER_FILTERED, "Buyer {$integration->name} filtered: ineligible", [
          'integration_id' => $integration->id,
          'reason' => 'ineligible',
          'detail' => $skipReason,
        ]);
        $this->bufferBuyerEvent($dispatch->id, $integration->id, 'filtered', 'ineligible', $skipReason);
        return false;
      }

      if ($this->caps->isCapExceeded($integration)) {
        $this->timeline->log(DispatchTimelineService::BUYER_FILTERED, "Buyer {$integration->name} filtered: cap exceeded", [
          'integration_id' => $integration->id,
          'reason' => 'cap_exceeded',
        ]);
        $this->bufferBuyerEvent($dispatch->id, $integration->id, 'filtered', 'cap_exceeded');
        return false;
      }

      return true;
    });

    $this->timeline->log(
      DispatchTimelineService::ELIGIBILITY_CHECK,
      "{$eligible->count()} of {$allBuyers->count()} buyers eligible for {$group} group",
      [
        'total' => $allBuyers->count(),
        'passed' => $eligible->count(),
        'group' => $group,
      ],
    );

    return $eligible;
  }

  private function isDuplicate(LeadDispatch $dispatch, Integration $integration): bool
  {
    $key = LeadDispatch::generateIdempotencyKey($dispatch->workflow_id, $integration->id, $dispatch->fingerprint);

    return PingResult::where('idempotency_key', $key)->exists();
  }

  private function extractBidFromResponse(\Illuminate\Http\Client\Response $response, ?\App\Models\PingResponseConfig $config): ?float
  {
    $path = $config?->bid_price_path;

    if (!$path) {
      return null;
    }

    $value = \Illuminate\Support\Arr::get($response->json() ?? [], $path);

    return is_numeric($value) ? (float) $value : null;
  }

  private function isAcceptedResponse(\Illuminate\Http\Client\Response $response, ?\App\Models\PingResponseConfig $config): bool
  {
    $acceptedPath = $config?->accepted_path;
    $acceptedValue = $config?->accepted_value;

    if (!$acceptedPath) {
      return $response->successful();
    }

    $actual = \Illuminate\Support\Arr::get($response->json() ?? [], $acceptedPath);

    return (string) $actual === (string) $acceptedValue;
  }

  private function bufferBuyerEvent(int $dispatchId, int $integrationId, string $event, string $reason, ?string $detail = null): void
  {
    TailLogger::saveLog('BuyerEvent buffered', 'dispatch/buyer-events', 'info', [
      'dispatch_id' => $dispatchId,
      'integration_id' => $integrationId,
      'event' => $event,
      'reason' => $reason,
      'detail' => $detail,
    ]);

    $this->buyerEventsBuffer[] = [
      'lead_dispatch_id' => $dispatchId,
      'integration_id' => $integrationId,
      'event' => $event,
      'reason' => $reason,
      'detail' => $detail,
    ];
  }

  private function flushBuyerEvents(): void
  {
    if (empty($this->buyerEventsBuffer)) {
      TailLogger::saveLog('BuyerEvents flush: empty buffer', 'dispatch/buyer-events');
      return;
    }

    TailLogger::saveLog('BuyerEvents flush', 'dispatch/buyer-events', 'info', [
      'count' => count($this->buyerEventsBuffer),
    ]);

    FlushBuyerEventsJob::dispatch($this->buyerEventsBuffer);
    $this->buyerEventsBuffer = [];
  }
}
