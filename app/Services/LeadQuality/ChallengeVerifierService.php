<?php

namespace App\Services\LeadQuality;

use App\Enums\DispatchStatus;
use App\Enums\LeadQuality\ValidationLogStatus;
use App\Jobs\PingPost\DispatchLeadJob;
use App\Models\LeadDispatch;
use App\Models\LeadQualityValidationLog;
use App\Services\PingPost\DispatchTimelineService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Handles `challenge/verify`: resolves a signed challenge_token back to a
 * validation log, delegates the actual verification to the configured
 * provider, and on success transitions the associated `LeadDispatch` from
 * `PENDING_VALIDATION` to `RUNNING` and enqueues the dispatch job.
 *
 * On failure beyond `max_attempts`, marks both the log and the dispatch
 * as terminal (`FAILED` / `VALIDATION_FAILED`).
 */
class ChallengeVerifierService
{
  public function __construct(private readonly LeadQualityProviderResolver $resolver, private readonly DispatchTimelineService $timeline) {}

  /**
   * Result shape returned to the frontend.
   *
   * @param  array{to?: string}  $context  Destination (phone/email) to forward to the provider check.
   * @return array{
   *   verified: bool,
   *   status: string,
   *   retry_remaining?: int,
   *   reason?: string,
   *   dispatch_uuid?: string,
   * }
   */
  public function verify(string $challengeToken, string $code, array $context = []): array
  {
    $decoded = $this->decodeToken($challengeToken);
    if (!$decoded) {
      return ['verified' => false, 'status' => 'invalid_token', 'reason' => 'challenge_token invalid or tampered'];
    }

    /** @var LeadQualityValidationLog|null $log */
    $log = LeadQualityValidationLog::query()->with('rule.provider')->find($decoded['log_id']);

    if (!$log || $log->fingerprint !== $decoded['fingerprint']) {
      return ['verified' => false, 'status' => 'not_found', 'reason' => 'validation log not found'];
    }

    if ($log->status === ValidationLogStatus::VERIFIED) {
      return ['verified' => true, 'status' => 'already_verified'];
    }

    if ($log->isExpired() || $log->status === ValidationLogStatus::EXPIRED) {
      $this->markDispatchValidationFailed($log, 'challenge expired');
      $log->markExpired();
      return ['verified' => false, 'status' => 'expired', 'reason' => 'challenge expired'];
    }

    if ($log->status === ValidationLogStatus::FAILED) {
      return ['verified' => false, 'status' => 'already_failed', 'reason' => 'challenge already marked as failed'];
    }

    $rule = $log->rule;
    $provider = $rule?->provider;

    if (!$rule || !$provider) {
      $log->update(['status' => ValidationLogStatus::ERROR, 'message' => 'rule or provider missing']);
      return ['verified' => false, 'status' => 'error', 'reason' => 'rule or provider missing'];
    }

    try {
      $service = $this->resolver->forProvider($provider);
    } catch (\Throwable $e) {
      $log->update(['status' => ValidationLogStatus::ERROR, 'message' => "resolver: {$e->getMessage()}"]);
      return ['verified' => false, 'status' => 'error', 'reason' => $e->getMessage()];
    }

    try {
      $result = $service->verifyChallenge($provider, $rule, $log, $code, $context);
    } catch (\Throwable $e) {
      Log::error('ChallengeVerifier verifyChallenge threw', ['log_id' => $log->id, 'exception' => $e->getMessage()]);
      $log->update(['status' => ValidationLogStatus::ERROR, 'message' => $e->getMessage()]);
      return ['verified' => false, 'status' => 'error', 'reason' => $e->getMessage()];
    }

    if ($result->verified) {
      $log->markVerified('verified by provider');
      $dispatch = $this->transitionDispatchToRunning($log);

      return [
        'verified' => true,
        'status' => 'verified',
        'dispatch_uuid' => $dispatch?->dispatch_uuid,
      ];
    }

    // Failed attempt — increment and decide whether to close out.
    $log->incrementAttempt();
    $refreshed = $log->fresh();
    $maxAttempts = $rule->maxAttempts();

    if ($refreshed && $refreshed->attempts_count >= $maxAttempts) {
      $refreshed->markFailed($result->error ?? 'max attempts reached');
      $this->markDispatchValidationFailed($refreshed, 'max attempts reached');

      return ['verified' => false, 'status' => 'failed', 'reason' => $result->error ?? 'max attempts reached'];
    }

    $remaining = max(0, $maxAttempts - ($refreshed?->attempts_count ?? 0));
    $attemptNumber = $refreshed?->attempts_count ?? 1;

    if ($log->lead_dispatch_id) {
      $this->timeline->logSingle(
        $log->lead_dispatch_id,
        (string) $log->fingerprint,
        'challenge.attempt_failed',
        "Wrong code (attempt {$attemptNumber}/{$maxAttempts})",
        [
          'rule_id' => $rule->id,
          'rule_name' => $rule->name,
          'validation_log_id' => $log->id,
          'attempt_number' => $attemptNumber,
          'retry_remaining' => $remaining,
          'reason' => $result->error,
        ],
      );
    }

    return [
      'verified' => false,
      'status' => 'retry',
      'retry_remaining' => $remaining,
      'reason' => $result->error ?? 'code did not match',
    ];
  }

  /**
   * @return array{log_id: int, fingerprint: string}|null
   */
  private function decodeToken(string $token): ?array
  {
    try {
      $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
    } catch (\Throwable) {
      return null;
    }

    if (!is_array($payload) || !isset($payload['log_id'], $payload['fingerprint'])) {
      return null;
    }

    return ['log_id' => (int) $payload['log_id'], 'fingerprint' => (string) $payload['fingerprint']];
  }

  private function transitionDispatchToRunning(LeadQualityValidationLog $log): ?LeadDispatch
  {
    $dispatch = $log->leadDispatch()->first();

    if (!$dispatch) {
      return null;
    }

    if ($dispatch->status !== DispatchStatus::PENDING_VALIDATION) {
      return $dispatch;
    }

    $dispatch->update(['status' => DispatchStatus::RUNNING]);

    $this->timeline->logSingle(
      $dispatch->id,
      (string) $dispatch->fingerprint,
      DispatchTimelineService::VALIDATION_COMPLETED,
      "Challenge verified for rule '{$log->rule?->name}'; dispatch resumed",
      [
        'rule_id' => $log->validation_rule_id,
        'rule_name' => $log->rule?->name,
        'validation_log_id' => $log->id,
        'attempts_used' => $log->attempts_count,
      ],
    );

    DispatchLeadJob::dispatch($dispatch->workflow_id, $dispatch->lead_id, $dispatch->fingerprint, $dispatch->id);

    return $dispatch->fresh();
  }

  private function markDispatchValidationFailed(LeadQualityValidationLog $log, string $reason): void
  {
    $dispatch = $log->leadDispatch()->first();

    if (!$dispatch || $dispatch->status->isTerminal()) {
      return;
    }

    $this->timeline->logSingle(
      $dispatch->id,
      (string) $dispatch->fingerprint,
      DispatchTimelineService::VALIDATION_FAILED,
      "Challenge failed: {$reason}",
      [
        'rule_id' => $log->validation_rule_id,
        'rule_name' => $log->rule?->name,
        'validation_log_id' => $log->id,
        'reason' => $reason,
        'attempts_used' => $log->attempts_count,
      ],
    );

    $dispatch->update(['status' => DispatchStatus::VALIDATION_FAILED]);
  }
}
