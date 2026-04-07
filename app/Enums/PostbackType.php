<?php

namespace App\Enums;

enum PostbackType: string
{
  case EXTERNAL = 'external';
  case INTERNAL = 'internal';

  public function label(): string
  {
    return match ($this) {
      self::EXTERNAL => 'External',
      self::INTERNAL => 'Internal',
    };
  }

  /**
   * @return array<int, array{value: string, label: string}>
   */
  public static function toArray(): array
  {
    return array_map(
      fn(self $type) => [
        'value' => $type->value,
        'label' => $type->label(),
      ],
      self::cases(),
    );
  }
}
