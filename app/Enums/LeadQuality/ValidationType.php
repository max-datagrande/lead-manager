<?php

namespace App\Enums\LeadQuality;

enum ValidationType: string
{
  case OTP_PHONE = 'otp_phone';
  case OTP_EMAIL = 'otp_email';
  case PHONE_LOOKUP = 'phone_lookup';
  case EMAIL_REPUTATION = 'email_reputation';
  case IPQS_SCORE = 'ipqs_score';

  public function label(): string
  {
    return match ($this) {
      self::OTP_PHONE => 'OTP Phone',
      self::OTP_EMAIL => 'OTP Email',
      self::PHONE_LOOKUP => 'Phone Lookup',
      self::EMAIL_REPUTATION => 'Email Reputation',
      self::IPQS_SCORE => 'IPQS Score',
    };
  }

  /**
   * Whether the validation requires user interaction (async challenge flow).
   * Async validations are resolved via public API and produce pending_validation dispatches;
   * sync validations run inline inside the eligibility gate.
   */
  public function isAsync(): bool
  {
    return match ($this) {
      self::OTP_PHONE, self::OTP_EMAIL => true,
      self::PHONE_LOOKUP, self::EMAIL_REPUTATION, self::IPQS_SCORE => false,
    };
  }

  /**
   * @return array<int, array{value: string, label: string, is_async: bool}>
   */
  public static function toArray(): array
  {
    return array_map(
      fn(self $type) => [
        'value' => $type->value,
        'label' => $type->label(),
        'is_async' => $type->isAsync(),
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
