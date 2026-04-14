<?php

namespace App\Services\PingPost;

use App\Jobs\PingPost\FlushTimelineLogsJob;
use Carbon\CarbonImmutable;

/**
 * Structured timeline logger for lead dispatch events.
 *
 * Collects events in an in-memory buffer during orchestration,
 * then flushes them as a single batch via a queue job.
 *
 * Usage:
 *   $this->timeline->bind($fingerprint, $dispatch->id);
 *   $this->timeline->log(self::PING_RESULT, 'Buyer X: accepted', ['ping_result_id' => 1]);
 *   $this->timeline->flush(); // in finally block
 */
class DispatchTimelineService
{
  // ─── Dispatch lifecycle ─────────────────────────────────────────────
  public const DISPATCH_STARTED = 'dispatch.started';
  public const DISPATCH_COMPLETED = 'dispatch.completed';
  public const DISPATCH_ERROR = 'dispatch.error';

  // ─── Eligibility & filtering ────────────────────────────────────────
  public const ELIGIBILITY_CHECK = 'eligibility.check';
  public const BUYER_FILTERED = 'buyer.filtered';

  // ─── Ping ───────────────────────────────────────────────────────────
  public const PING_RESULT = 'ping.result';
  public const PING_ERROR = 'ping.error';
  public const PING_PARALLEL_COMPLETE = 'ping.parallel_complete';

  // ─── Price ──────────────────────────────────────────────────────────
  public const PRICE_RESOLVED = 'price.resolved';
  public const PRICE_SKIPPED = 'price.skipped';

  // ─── Post ───────────────────────────────────────────────────────────
  public const POST_RESULT = 'post.result';
  public const POST_ERROR = 'post.error';

  // ─── Cascade ────────────────────────────────────────────────────────
  public const CASCADE_ADVANCE = 'cascade.advance';
  public const CASCADE_BREAK = 'cascade.break';

  // ─── Fallback ───────────────────────────────────────────────────────
  public const FALLBACK_ACTIVATED = 'fallback.activated';
  public const FALLBACK_NO_BUYERS = 'fallback.no_buyers';

  // ─── Outcome ────────────────────────────────────────────────────────
  public const OUTCOME_SOLD = 'outcome.sold';
  public const OUTCOME_NOT_SOLD = 'outcome.not_sold';
  public const OUTCOME_PENDING_POSTBACK = 'outcome.pending_postback';

  // ─── Postback ───────────────────────────────────────────────────────
  public const POSTBACK_FIRED = 'postback.fired';
  public const POSTBACK_FIRE_FAILED = 'postback.fire_failed';

  /** @var array<int, array{fingerprint: string, lead_dispatch_id: int, event: string, message: string, context: ?array, logged_at: string}> */
  private array $buffer = [];
  private ?string $fingerprint = null;
  private ?int $leadDispatchId = null;

  /**
   * Bind to a specific dispatch context. Called once at orchestration start.
   */
  public function bind(string $fingerprint, int $leadDispatchId): void
  {
    $this->fingerprint = $fingerprint;
    $this->leadDispatchId = $leadDispatchId;
    $this->buffer = [];
  }

  /**
   * Record an event into the in-memory buffer.
   */
  public function log(string $event, string $message, array $context = []): void
  {
    if ($this->fingerprint === null || $this->leadDispatchId === null) {
      return;
    }

    $this->buffer[] = [
      'fingerprint' => $this->fingerprint,
      'lead_dispatch_id' => $this->leadDispatchId,
      'event' => $event,
      'message' => $message,
      'context' => !empty($context) ? $context : null,
      'logged_at' => CarbonImmutable::now()->format('Y-m-d H:i:s.u'),
    ];
  }

  /**
   * Dispatch all buffered events to a queue job for async DB insertion.
   */
  public function flush(): void
  {
    if (empty($this->buffer)) {
      return;
    }

    FlushTimelineLogsJob::dispatch($this->buffer);
    $this->buffer = [];
  }

  /**
   * Get the current buffer contents (useful for testing).
   *
   * @return array<int, array>
   */
  public function getBuffer(): array
  {
    return $this->buffer;
  }
}
