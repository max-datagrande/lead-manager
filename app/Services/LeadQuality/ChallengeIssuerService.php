<?php

namespace App\Services\LeadQuality;

use App\Enums\DispatchStatus;
use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationLogStatus;
use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use App\Models\Workflow;
use App\Services\PingPost\DispatchTimelineService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Creates a `LeadDispatch` in `PENDING_VALIDATION` state and issues a
 * validation challenge (OTP, etc.) via the configured provider for each
 * applicable rule of the workflow's buyers.
 *
 * Returns a signed `challenge_token` per issued challenge for the frontend
 * to submit alongside the user-entered code.
 */
class ChallengeIssuerService
{
  public function __construct(private readonly LeadQualityProviderResolver $resolver, private readonly DispatchTimelineService $timeline) {}

  /**
   * Issue validation challenges for a workflow + lead pair.
   *
   * @param  array{to?: string, channel?: string, locale?: string}  $context  Per-channel delivery options.
   * @return array{
   *   dispatch_id: int,
   *   dispatch_uuid: string,
   *   challenges: array<int, array{
   *     challenge_token: string,
   *     rule_id: int,
   *     rule_name: string,
   *     channel: ?string,
   *     masked_destination: ?string,
   *     expires_at: string,
   *   }>,
   *   errors: array<int, array{rule_id: int, rule_name: string, error: string}>,
   * }
   */
  public function issue(Workflow $workflow, Lead $lead, string $fingerprint, array $context = []): array
  {
    $rules = $this->resolveApplicableRules($workflow);

    $dispatch = LeadDispatch::create([
      'workflow_id' => $workflow->id,
      'lead_id' => $lead->id,
      'fingerprint' => $fingerprint,
      'status' => DispatchStatus::PENDING_VALIDATION,
      'strategy_used' => $workflow->strategy?->value,
      'started_at' => now(),
    ]);

    $challenges = [];
    $errors = [];

    foreach ($rules as $rule) {
      $log = $this->createPendingLog($rule, $dispatch, $lead, $fingerprint, $context);

      $this->timeline->logSingle(
        $dispatch->id,
        $fingerprint,
        DispatchTimelineService::VALIDATION_STARTED,
        "Challenge issued for rule '{$rule->name}'",
        [
          'rule_id' => $rule->id,
          'rule_name' => $rule->name,
          'validation_type' => $rule->validation_type?->value,
          'provider_id' => $rule->provider_id,
          'provider_name' => $rule->provider?->name,
          'validation_log_id' => $log->id,
        ],
      );

      try {
        $service = $this->resolver->forProvider($rule->provider);
      } catch (\Throwable $e) {
        $log->update([
          'status' => ValidationLogStatus::ERROR,
          'message' => "Provider resolve failed: {$e->getMessage()}",
          'resolved_at' => now(),
        ]);
        $errors[] = ['rule_id' => $rule->id, 'rule_name' => $rule->name, 'error' => $e->getMessage()];
        continue;
      }

      try {
        $result = $service->sendChallenge($rule->provider, $rule, $log, $context);
      } catch (\Throwable $e) {
        Log::error('ChallengeIssuer sendChallenge threw', [
          'rule_id' => $rule->id,
          'exception' => $e->getMessage(),
        ]);
        $log->update([
          'status' => ValidationLogStatus::ERROR,
          'message' => "sendChallenge exception: {$e->getMessage()}",
          'resolved_at' => now(),
        ]);
        $errors[] = ['rule_id' => $rule->id, 'rule_name' => $rule->name, 'error' => $e->getMessage()];
        continue;
      }

      if (!$result->sent) {
        $log->update([
          'status' => ValidationLogStatus::FAILED,
          'message' => $result->error,
          'result' => 'fail',
          'resolved_at' => now(),
        ]);
        $this->timeline->logSingle(
          $dispatch->id,
          $fingerprint,
          'challenge.send_failed',
          "Provider rejected challenge for rule '{$rule->name}': {$result->error}",
          ['rule_id' => $rule->id, 'validation_log_id' => $log->id, 'error' => $result->error],
        );
        $errors[] = ['rule_id' => $rule->id, 'rule_name' => $rule->name, 'error' => $result->error ?? 'Challenge not sent'];
        continue;
      }

      $expiresAt = now()->addSeconds($rule->ttlSeconds());

      $log->update([
        'status' => ValidationLogStatus::SENT,
        'challenge_reference' => $result->reference,
        'expires_at' => $expiresAt,
        'context' => array_merge($log->context ?? [], [
          'channel' => $context['channel'] ?? ($rule->settings['channel'] ?? null),
          'masked_destination' => $result->maskedDestination,
        ]),
      ]);

      $channel = $context['channel'] ?? ($rule->settings['channel'] ?? 'sms');
      $dest = $result->maskedDestination ?? 'destination hidden';
      $this->timeline->logSingle($dispatch->id, $fingerprint, 'challenge.sent', "Challenge sent via {$channel} to {$dest}", [
        'rule_id' => $rule->id,
        'validation_log_id' => $log->id,
        'channel' => $channel,
        'masked_destination' => $result->maskedDestination,
        'challenge_reference' => $result->reference,
        'expires_at' => $expiresAt->toIso8601String(),
      ]);

      $challenges[] = [
        'challenge_token' => $this->encodeToken($log->id, $fingerprint),
        'rule_id' => $rule->id,
        'rule_name' => $rule->name,
        'channel' => $context['channel'] ?? ($rule->settings['channel'] ?? null),
        'masked_destination' => $result->maskedDestination,
        'expires_at' => $expiresAt->toIso8601String(),
      ];
    }

    // If every rule failed to send, short-circuit the dispatch as validation_failed.
    if ($challenges === [] && $errors !== []) {
      $dispatch->update(['status' => DispatchStatus::VALIDATION_FAILED]);
      $this->timeline->logSingle(
        $dispatch->id,
        $fingerprint,
        DispatchTimelineService::VALIDATION_FAILED,
        'All applicable challenges failed to send; dispatch closed',
        ['errors' => $errors],
      );
    }

    return [
      'dispatch_id' => $dispatch->id,
      'dispatch_uuid' => $dispatch->dispatch_uuid,
      'challenges' => $challenges,
      'errors' => $errors,
    ];
  }

  /**
   * @return Collection<int, LeadQualityValidationRule>
   */
  private function resolveApplicableRules(Workflow $workflow): Collection
  {
    $integrationIds = $workflow->workflowBuyers()->where('is_active', true)->pluck('integration_id');

    if ($integrationIds->isEmpty()) {
      return collect();
    }

    return LeadQualityValidationRule::query()
      ->active()
      ->whereHas('buyers', fn($q) => $q->whereIn('integrations.id', $integrationIds)->where('buyer_validation_rule.is_enabled', true))
      ->with('provider')
      ->get()
      ->unique('id');
  }

  /**
   * @param  array<string, mixed>  $context
   */
  private function createPendingLog(
    LeadQualityValidationRule $rule,
    LeadDispatch $dispatch,
    Lead $lead,
    string $fingerprint,
    array $context,
  ): LeadQualityValidationLog {
    return LeadQualityValidationLog::create([
      'validation_rule_id' => $rule->id,
      'provider_id' => $rule->provider_id,
      'lead_id' => $lead->id,
      'lead_dispatch_id' => $dispatch->id,
      'fingerprint' => $fingerprint,
      'status' => ValidationLogStatus::PENDING,
      'attempts_count' => 0,
      'context' => array_filter([
        'channel' => $context['channel'] ?? ($rule->settings['channel'] ?? null),
        'to_hint' => isset($context['to']) ? $this->maskInput((string) $context['to']) : null,
      ]),
      'started_at' => now(),
    ]);
  }

  private function encodeToken(int $logId, string $fingerprint): string
  {
    return Crypt::encryptString(json_encode(['log_id' => $logId, 'fingerprint' => $fingerprint]));
  }

  private function maskInput(string $value): string
  {
    if (str_contains($value, '@')) {
      [$user, $domain] = explode('@', $value, 2);
      $head = substr($user, 0, 2);
      return "{$head}***@{$domain}";
    }

    $digits = preg_replace('/\D/', '', $value) ?? '';
    $last = substr($digits, -4);
    $prefix = str_starts_with($value, '+') ? '+' : '';

    return "{$prefix}" . str_repeat('*', max(0, strlen($digits) - 4)) . $last;
  }
}
