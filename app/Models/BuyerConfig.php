<?php

namespace App\Models;

use App\Enums\PriceSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerConfig extends Model
{
  protected $fillable = [
    'integration_id',
    'ping_timeout_ms',
    'post_timeout_ms',
    'price_source',
    'fixed_price',
    'min_bid',
    'conditional_pricing_rules',
    'postback_pending_days',
    'sell_on_zero_price',
  ];

  protected $casts = [
    'conditional_pricing_rules' => 'array',
    'price_source' => PriceSource::class,
    'sell_on_zero_price' => 'boolean',
    'fixed_price' => 'decimal:4',
    'min_bid' => 'decimal:4',
  ];

  public function integration(): BelongsTo
  {
    return $this->belongsTo(Integration::class);
  }
}
