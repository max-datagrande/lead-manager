<?php

namespace App\Enums;

enum PostResultStatus: string
{
  case POSTED = 'posted';
  case ACCEPTED = 'accepted';
  case REJECTED = 'rejected';
  case ERROR = 'error';
  case TIMEOUT = 'timeout';
  case RETRY_QUEUED = 'retry_queued';
  case PENDING_POSTBACK = 'pending_postback';
  case POSTBACK_RESOLVED = 'postback_resolved';
  case SKIPPED = 'skipped';

  public function label(): string
  {
    return match ($this) {
      self::POSTED => 'Posted',
      self::ACCEPTED => 'Accepted',
      self::REJECTED => 'Rejected',
      self::ERROR => 'Error',
      self::TIMEOUT => 'Timeout',
      self::RETRY_QUEUED => 'Retry Queued',
      self::PENDING_POSTBACK => 'Pending Postback',
      self::POSTBACK_RESOLVED => 'Postback Resolved',
      self::SKIPPED => 'Skipped',
    };
  }

  public function icon(): string
  {
    return match ($this) {
      self::POSTED => 'send',
      self::ACCEPTED => 'check-circle',
      self::REJECTED => 'x-circle',
      self::ERROR => 'alert-circle',
      self::TIMEOUT => 'timer-off',
      self::RETRY_QUEUED => 'refresh-cw',
      self::PENDING_POSTBACK => 'clock',
      self::POSTBACK_RESOLVED => 'check-check',
      self::SKIPPED => 'minus-circle',
    };
  }

  public function isSold(): bool
  {
    return in_array($this, [self::ACCEPTED, self::POSTBACK_RESOLVED]);
  }

  public function isTerminal(): bool
  {
    return in_array($this, [self::ACCEPTED, self::REJECTED, self::POSTBACK_RESOLVED, self::SKIPPED]);
  }

  public function isPendingResolution(): bool
  {
    return in_array($this, [self::PENDING_POSTBACK, self::RETRY_QUEUED]);
  }

  /**
   * @return array<int, array{value: string, label: string}>
   */
  public static function toArray(): array
  {
    return array_map(
      fn(self $status) => [
        'value' => $status->value,
        'label' => $status->label(),
      ],
      self::cases(),
    );
  }
}
