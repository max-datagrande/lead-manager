<?php

namespace App\Enums;

enum PricingType: string
{
    case FIXED = 'fixed';
    case MIN_BID = 'min_bid';
    case CONDITIONAL = 'conditional';
    case POSTBACK = 'postback';

    public function label(): string
    {
        return match ($this) {
            self::FIXED => 'Fixed Price',
            self::MIN_BID => 'Min Bid',
            self::CONDITIONAL => 'Conditional',
            self::POSTBACK => 'Postback',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FIXED => 'A fixed price is always applied regardless of the bid.',
            self::MIN_BID => 'Accept the bid only if it meets or exceeds the minimum threshold.',
            self::CONDITIONAL => 'Price is determined by matching lead field conditions.',
            self::POSTBACK => 'Price is resolved asynchronously via postback from the buyer.',
        };
    }

    public function requiresPing(): bool
    {
        return $this === self::MIN_BID;
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
            fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ],
            self::cases(),
        );
    }
}
