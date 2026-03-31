<?php

use App\Enums\PricingType;
use App\Models\BuyerConfig;
use App\Services\PingPost\PriceResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

$config = fn (array $attrs) => new BuyerConfig($attrs);

// ─── Fixed ───────────────────────────────────────────────────────────────────

it('returns fixed_price regardless of bid for FIXED type', function () use ($config) {
    $c = $config(['pricing_type' => PricingType::FIXED, 'fixed_price' => 15.00, 'min_bid' => null]);

    $resolver = app(PriceResolverService::class);

    expect($resolver->resolvePrice($c, 0.00))->toBe(15.0)
        ->and($resolver->resolvePrice($c, 99.99))->toBe(15.0);
});

// ─── Min bid ─────────────────────────────────────────────────────────────────

it('returns bid when bid meets min_bid threshold', function () use ($config) {
    $c = $config(['pricing_type' => PricingType::MIN_BID, 'min_bid' => 5.00, 'fixed_price' => null]);

    expect(app(PriceResolverService::class)->resolvePrice($c, 5.00))->toBe(5.0);
    expect(app(PriceResolverService::class)->resolvePrice($c, 9.99))->toBe(9.99);
});

it('returns null when bid is below min_bid', function () use ($config) {
    $c = $config(['pricing_type' => PricingType::MIN_BID, 'min_bid' => 10.00, 'fixed_price' => null]);

    expect(app(PriceResolverService::class)->resolvePrice($c, 9.99))->toBeNull();
});

it('isPriceAcceptable returns true for FIXED type always', function () use ($config) {
    $c = $config(['pricing_type' => PricingType::FIXED, 'min_bid' => null]);

    expect(app(PriceResolverService::class)->isPriceAcceptable($c, 0))->toBeTrue();
});

it('isPriceAcceptable uses min_bid threshold for MIN_BID type', function () use ($config) {
    $c = $config(['pricing_type' => PricingType::MIN_BID, 'min_bid' => 5.00]);

    $resolver = app(PriceResolverService::class);
    expect($resolver->isPriceAcceptable($c, 5.00))->toBeTrue();
    expect($resolver->isPriceAcceptable($c, 4.99))->toBeFalse();
});

// ─── Conditional ─────────────────────────────────────────────────────────────

it('resolves conditional price from first matching rule', function () use ($config) {
    $c = $config([
        'pricing_type' => PricingType::CONDITIONAL,
        'conditional_pricing_rules' => [
            ['conditions' => [['field' => 'state', 'op' => 'eq', 'value' => 'CA']], 'price' => 20.0],
            ['conditions' => [['field' => 'state', 'op' => 'eq', 'value' => 'TX']], 'price' => 12.0],
        ],
    ]);

    $resolver = app(PriceResolverService::class);

    expect($resolver->resolveConditionalPrice($c, ['state' => 'CA']))->toBe(20.0);
    expect($resolver->resolveConditionalPrice($c, ['state' => 'TX']))->toBe(12.0);
});

it('returns null when no conditional rule matches', function () use ($config) {
    $c = $config([
        'pricing_type' => PricingType::CONDITIONAL,
        'conditional_pricing_rules' => [
            ['conditions' => [['field' => 'state', 'op' => 'eq', 'value' => 'CA']], 'price' => 20.0],
        ],
    ]);

    expect(app(PriceResolverService::class)->resolveConditionalPrice($c, ['state' => 'FL']))->toBeNull();
});

it('resolves conditional price with multiple conditions (AND)', function () use ($config) {
    $c = $config([
        'pricing_type' => PricingType::CONDITIONAL,
        'conditional_pricing_rules' => [
            [
                'conditions' => [
                    ['field' => 'state', 'op' => 'eq', 'value' => 'CA'],
                    ['field' => 'age',   'op' => 'gte', 'value' => 25],
                ],
                'price' => 30.0,
            ],
        ],
    ]);

    $resolver = app(PriceResolverService::class);

    expect($resolver->resolveConditionalPrice($c, ['state' => 'CA', 'age' => 30]))->toBe(30.0);
    expect($resolver->resolveConditionalPrice($c, ['state' => 'CA', 'age' => 20]))->toBeNull();
});

// ─── Postback (async) ────────────────────────────────────────────────────────

it('returns null for POSTBACK pricing type (async resolution)', function () use ($config) {
    $c = $config(['pricing_type' => PricingType::POSTBACK, 'fixed_price' => null, 'min_bid' => null]);

    expect(app(PriceResolverService::class)->resolvePrice($c, 99.0))->toBeNull();
});
