<?php

namespace App\Enums;

enum DispatchStatus: string
{
  case PENDING = 'pending';
  case RUNNING = 'running';
  case SOLD = 'sold';
  case NOT_SOLD = 'not_sold';
  case ERROR = 'error';
  case TIMEOUT = 'timeout';

  public function label(): string
  {
    return match ($this) {
      self::PENDING => 'Pending',
      self::RUNNING => 'Running',
      self::SOLD => 'Sold',
      self::NOT_SOLD => 'Not Sold',
      self::ERROR => 'Error',
      self::TIMEOUT => 'Timeout',
    };
  }

  public function icon(): string
  {
    return match ($this) {
      self::PENDING => 'clock',
      self::RUNNING => 'loader',
      self::SOLD => 'check-circle',
      self::NOT_SOLD => 'x-circle',
      self::ERROR => 'alert-circle',
      self::TIMEOUT => 'timer-off',
    };
  }

  public function color(): string
  {
    return match ($this) {
      self::PENDING => 'gray',
      self::RUNNING => 'blue',
      self::SOLD => 'green',
      self::NOT_SOLD => 'red',
      self::ERROR => 'orange',
      self::TIMEOUT => 'yellow',
    };
  }

  public function isTerminal(): bool
  {
    return in_array($this, [self::SOLD, self::NOT_SOLD, self::ERROR, self::TIMEOUT]);
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
