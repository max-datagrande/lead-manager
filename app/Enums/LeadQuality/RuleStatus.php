<?php

namespace App\Enums\LeadQuality;

enum RuleStatus: string
{
  case DRAFT = 'draft';
  case ACTIVE = 'active';
  case INACTIVE = 'inactive';

  public function label(): string
  {
    return match ($this) {
      self::DRAFT => 'Draft',
      self::ACTIVE => 'Active',
      self::INACTIVE => 'Inactive',
    };
  }

  public function color(): string
  {
    return match ($this) {
      self::DRAFT => 'yellow',
      self::ACTIVE => 'green',
      self::INACTIVE => 'gray',
    };
  }

  public function isActive(): bool
  {
    return $this === self::ACTIVE;
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
