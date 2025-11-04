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
        'company_id',
        'amount',
        'fingerprint',
        'click_id',
        'utm_source',
        'utm_medium',
    ];

    /**
     * Get the integration that owns the conversion.
     */
    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Get the company that owns the conversion.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}