<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferwallConversion extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'integration_id',
        'amount',
        'fingerprint',
        'click_id',
        'utm_source',
        'utm_medium',
        'offerwall_mix_log_id',
        'offer_data',
        'pathname',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'offer_data' => 'array',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the integration that owns the conversion.
     */
    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }
}
