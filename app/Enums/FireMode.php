<?php

namespace App\Enums;

enum FireMode: string
{
  case REALTIME = 'realtime';
  case DEFERRED = 'deferred';

  public function label(): string
  {
    return match ($this) {
      self::REALTIME => 'Realtime',
      self::DEFERRED => 'Deferred',
    };
  }

  /**
   * @return array<int, array{value: string, label: string}>
   */
  public static function toArray(): array
  {
    return array_map(
      fn(self $mode) => [
        'value' => $mode->value,
        'label' => $mode->label(),
      ],
      self::cases(),
    );
  }
}
