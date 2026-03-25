<?php

namespace App\Models;

use App\Enums\PricingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerConfig extends Model
{
    protected $fillable = [
        'integration_id',
        'ping_url',
        'ping_method',
        'ping_headers',
        'ping_body',
        'ping_timeout_ms',
        'post_url',
        'post_method',
        'post_headers',
        'post_body',
        'post_timeout_ms',
        'ping_response_config',
        'post_response_config',
        'pricing_type',
        'fixed_price',
        'min_bid',
        'conditional_pricing_rules',
        'postback_pending_days',
    ];

    protected $casts = [
        'ping_headers' => 'array',
        'post_headers' => 'array',
        'ping_response_config' => 'array',
        'post_response_config' => 'array',
        'conditional_pricing_rules' => 'array',
        'pricing_type' => PricingType::class,
        'fixed_price' => 'decimal:4',
        'min_bid' => 'decimal:4',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
