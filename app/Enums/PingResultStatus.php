<?php

namespace App\Enums;

enum PingResultStatus: string
{
  case SKIPPED = 'skipped';
  case DUPLICATE = 'duplicate';
  case INELIGIBLE = 'ineligible';
  case CAP_EXCEEDED = 'cap_exceeded';
  case ACCEPTED = 'accepted';
  case REJECTED = 'rejected';
  case TIMEOUT = 'timeout';
  case ERROR = 'error';

  public function label(): string
  {
    return match ($this) {
      self::SKIPPED => 'Skipped',
      self::DUPLICATE => 'Duplicate',
      self::INELIGIBLE => 'Ineligible',
      self::CAP_EXCEEDED => 'Cap Exceeded',
      self::ACCEPTED => 'Accepted',
      self::REJECTED => 'Rejected',
      self::TIMEOUT => 'Timeout',
      self::ERROR => 'Error',
    };
  }

  public function icon(): string
  {
    return match ($this) {
      self::SKIPPED => 'minus-circle',
      self::DUPLICATE => 'copy',
      self::INELIGIBLE => 'shield-off',
      self::CAP_EXCEEDED => 'trending-down',
      self::ACCEPTED => 'check-circle',
      self::REJECTED => 'x-circle',
      self::TIMEOUT => 'timer-off',
      self::ERROR => 'alert-circle',
    };
  }

  public function wasContacted(): bool
  {
    return in_array($this, [self::ACCEPTED, self::REJECTED, self::TIMEOUT, self::ERROR]);
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
