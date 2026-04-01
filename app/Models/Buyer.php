<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Buyer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'integration_id',
        'company_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Proxy to buyer_configs via integration_id so the execution layer stays compatible.
     */
    public function buyerConfig(): HasOne
    {
        return $this->hasOne(BuyerConfig::class, 'integration_id', 'integration_id');
    }

    public function eligibilityRules(): HasMany
    {
        return $this->hasMany(BuyerEligibilityRule::class, 'integration_id', 'integration_id')
            ->orderBy('sort_order');
    }

    public function capRules(): HasMany
    {
        return $this->hasMany(BuyerCapRule::class, 'integration_id', 'integration_id');
    }
}
