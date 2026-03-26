<?php

namespace App\Models;

use App\Enums\PricingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerConfig extends Model
{
    protected $fillable = [
        'integration_id',
        'ping_timeout_ms',
        'post_timeout_ms',
        'pricing_type',
        'fixed_price',
        'min_bid',
        'conditional_pricing_rules',
        'postback_pending_days',
    ];

    protected $casts = [
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
