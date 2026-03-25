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
        'env_type',
        'method',
        'url',
        'request_body',
        'request_headers',
        'response_config',
        'content_type',
        'authentication_type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'response_config' => 'array',
    ];

    /**
     * Get the integration that owns the environment.
     */
    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }
}
