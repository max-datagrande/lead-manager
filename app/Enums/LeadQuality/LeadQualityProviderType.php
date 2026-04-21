<?php

namespace App\Enums\LeadQuality;

enum LeadQualityProviderType: string
{
  case TWILIO_VERIFY = 'twilio_verify';
  case IPQS = 'ipqs';
  case EMAIL_VALIDATOR = 'email_validator';

  public function label(): string
  {
    return match ($this) {
      self::TWILIO_VERIFY => 'Twilio Verify',
      self::IPQS => 'IPQS',
      self::EMAIL_VALIDATOR => 'Email Validator',
    };
  }

  public function isImplemented(): bool
  {
    return match ($this) {
      self::TWILIO_VERIFY => true,
      self::IPQS, self::EMAIL_VALIDATOR => false,
    };
  }

  /**
   * @return array<int, array{value: string, label: string, is_implemented: bool}>
   */
  public static function toArray(): array
  {
    return array_map(
      fn(self $type) => [
        'value' => $type->value,
        'label' => $type->label(),
        'is_implemented' => $type->isImplemented(),
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
