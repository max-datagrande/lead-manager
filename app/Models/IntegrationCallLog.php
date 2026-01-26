<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationCallLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'request_headers' => 'array',
        'request_payload' => 'array',
        'response_headers' => 'array',
        'response_body' => 'array',
        'original_field_values' => 'array',
        'mapped_field_values' => 'array',
    ];

    public function loggable()
    {
        return $this->morphTo();
    }

    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }
}
