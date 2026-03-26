<?php

namespace App\Enums;

enum WorkflowStrategy: string
{
    case BEST_BID = 'best_bid';
    case WATERFALL = 'waterfall';
    case COMBINED = 'combined';

    public function label(): string
    {
        return match ($this) {
            self::BEST_BID => 'Best Bid',
            self::WATERFALL => 'Waterfall',
            self::COMBINED => 'Combined',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BEST_BID => 'Ping all eligible buyers in parallel and post to the highest bidder.',
            self::WATERFALL => 'Send to buyers in order. Move to next on rejection or failure.',
            self::COMBINED => 'Best Bid on primary group, then Waterfall on secondary group if none accepts.',
        };
    }

    public function usesBidding(): bool
    {
        return in_array($this, [self::BEST_BID, self::COMBINED]);
    }

    public function usesOrdering(): bool
    {
        return in_array($this, [self::WATERFALL, self::COMBINED]);
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function toArray(): array
    {
        return array_map(
            fn (self $strategy) => [
                'value' => $strategy->value,
                'label' => $strategy->label(),
                'description' => $strategy->description(),
            ],
            self::cases(),
        );
    }
}
