<?php

namespace App\Enums;

enum PriceSource: string
{
  case FIXED = 'fixed';
  case RESPONSE_BID = 'response_bid';
  case CONDITIONAL = 'conditional';
  case POSTBACK = 'postback';

  public function label(): string
  {
    return match ($this) {
      self::FIXED => 'Fixed Price',
      self::RESPONSE_BID => 'Response Bid',
      self::CONDITIONAL => 'Conditional',
      self::POSTBACK => 'Postback',
    };
  }

  public function description(): string
  {
    return match ($this) {
      self::FIXED => 'A fixed price is always applied regardless of the bid.',
      self::RESPONSE_BID => 'Price is extracted from the buyer\'s ping response bid.',
      self::CONDITIONAL => 'Price is determined by matching lead field conditions.',
      self::POSTBACK => 'Price is resolved asynchronously via postback from the buyer.',
    };
  }

  public function requiresPing(): bool
  {
    return $this === self::RESPONSE_BID;
  }

  public function isAsync(): bool
  {
    return $this === self::POSTBACK;
  }

  /**
   * @return array<int, array{value: string, label: string, description: string}>
   */
  public static function toArray(): array
  {
    return array_map(
      fn(self $type) => [
        'value' => $type->value,
        'label' => $type->label(),
        'description' => $type->description(),
      ],
      self::cases(),
    );
  }
}
