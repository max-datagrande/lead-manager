<?php

namespace App\Enums\LeadQuality;

enum ValidationLogStatus: string
{
  case PENDING = 'pending';
  case SENT = 'sent';
  case VERIFIED = 'verified';
  case FAILED = 'failed';
  case EXPIRED = 'expired';
  case SKIPPED = 'skipped';
  case ERROR = 'error';

  public function label(): string
  {
    return match ($this) {
      self::PENDING => 'Pending',
      self::SENT => 'Sent',
      self::VERIFIED => 'Verified',
      self::FAILED => 'Failed',
      self::EXPIRED => 'Expired',
      self::SKIPPED => 'Skipped',
      self::ERROR => 'Error',
    };
  }

  public function color(): string
  {
    return match ($this) {
      self::PENDING => 'gray',
      self::SENT => 'blue',
      self::VERIFIED => 'green',
      self::FAILED => 'red',
      self::EXPIRED => 'yellow',
      self::SKIPPED => 'slate',
      self::ERROR => 'orange',
    };
  }

  public function isTerminal(): bool
  {
    return in_array($this, [self::VERIFIED, self::FAILED, self::EXPIRED, self::SKIPPED, self::ERROR]);
  }

  public function isPending(): bool
  {
    return in_array($this, [self::PENDING, self::SENT]);
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

  public static function fromValue(string $value): ?self
  {
    return self::tryFrom($value);
  }

  public static function isValid(string $value): bool
  {
    return self::tryFrom($value) !== null;
  }
}
