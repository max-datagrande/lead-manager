<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerCapRule extends Model
{
    protected $fillable = [
        'integration_id',
        'period',
        'max_leads',
        'max_revenue',
    ];

    protected $casts = [
        'max_revenue' => 'decimal:2',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
