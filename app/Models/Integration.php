<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'type',
        'is_active',
        'user_id',
        'updated_user_id',
    ];

    /**
     * Get the environments for the integration.
     */
    public function environments()
    {
        return $this->hasMany(IntegrationEnvironment::class);
    }

    /**
     * Get the company that owns the integration.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
