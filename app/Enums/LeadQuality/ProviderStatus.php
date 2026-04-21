<?php

namespace App\Enums\LeadQuality;

enum ProviderStatus: string
{
  case ACTIVE = 'active';
  case INACTIVE = 'inactive';
  case DISABLED = 'disabled';

  public function label(): string
  {
    return match ($this) {
      self::ACTIVE => 'Active',
      self::INACTIVE => 'Inactive',
      self::DISABLED => 'Disabled',
    };
  }

  public function color(): string
  {
    return match ($this) {
      self::ACTIVE => 'green',
      self::INACTIVE => 'gray',
      self::DISABLED => 'red',
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

  public static function fromValue(string $value): ?self
  {
    return self::tryFrom($value);
  }

  public static function isValid(string $value): bool
  {
    return self::tryFrom($value) !== null;
  }
}
