<?php

namespace App\Enums;

enum ExecutionStatus: string
{
  case PENDING = 'pending';
  case DISPATCHING = 'dispatching';
  case COMPLETED = 'completed';
  case FAILED = 'failed';
  case SKIPPED = 'skipped';

  public function message(): string
  {
    return match ($this) {
      self::PENDING => 'Postback received and queued for processing.',
      self::DISPATCHING => 'Postback is being dispatched.',
      self::COMPLETED => 'Postback processed successfully.',
      self::FAILED => 'Postback processing failed.',
      self::SKIPPED => 'Postback was skipped.',
    };
  }

  public function label(): string
  {
    return match ($this) {
      self::PENDING => 'Pending',
      self::DISPATCHING => 'Dispatching',
      self::COMPLETED => 'Completed',
      self::FAILED => 'Failed',
      self::SKIPPED => 'Skipped',
    };
  }

  public function icon(): string
  {
    return match ($this) {
      self::PENDING => 'clock',
      self::DISPATCHING => 'loader',
      self::COMPLETED => 'check-circle',
      self::FAILED => 'x-circle',
      self::SKIPPED => 'minus-circle',
    };
  }

  public function canTransitionTo(self $new): bool
  {
    return match ($this) {
      self::PENDING => in_array($new, [self::DISPATCHING, self::FAILED, self::SKIPPED]),
      self::DISPATCHING => in_array($new, [self::COMPLETED, self::FAILED, self::SKIPPED]),
      self::COMPLETED => false,
      self::FAILED => in_array($new, [self::PENDING]),
      self::SKIPPED => false,
    };
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
