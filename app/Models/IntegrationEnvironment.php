<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationEnvironment extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration_id',
        'environment',
        'method',
        'url',
        'request_body',
        'request_headers',
        'content_type',
        'authentication_type',
    ];

    /**
     * Get the integration that owns the environment.
     */
    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }
}
