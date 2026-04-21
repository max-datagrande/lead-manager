<?php

namespace App\Enums;

enum InternalToken: string
{
  case CLICK_ID = 'click_id';
  case TRANSACTION_ID = 'transaction_id';
  case PAYOUT = 'payout';
  case LEAD_ID = 'lead_id';
  case STATUS = 'status';
  case SOURCE = 'source';
  case CAMPAIGN_ID = 'campaign_id';
  case FINGERPRINT = 'fingerprint';

  public function label(): string
  {
    return match ($this) {
      self::CLICK_ID => 'Click ID',
      self::TRANSACTION_ID => 'Transaction ID',
      self::PAYOUT => 'Payout',
      self::LEAD_ID => 'Lead ID',
      self::STATUS => 'Status',
      self::SOURCE => 'Source',
      self::CAMPAIGN_ID => 'Campaign ID',
      self::FINGERPRINT => 'Fingerprint',
    };
  }

  /**
   * @return array<int, array{value: string, label: string}>
   */
  public static function toArray(): array
  {
    return array_map(
      fn(self $token) => [
        'value' => $token->value,
        'label' => $token->label(),
      ],
      self::cases(),
    );
  }

  public static function isValid(string $value): bool
  {
    return self::tryFrom($value) !== null;
  }
}
