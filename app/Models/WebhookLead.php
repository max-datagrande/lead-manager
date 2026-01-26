<?php

namespace App\Models;

use App\Enums\WebhookLeadStatus;
use Illuminate\Database\Eloquent\Model;

class WebhookLead extends Model
{
    protected $table = 'webhook_leads';

    protected $fillable = [
        'source',
        'payload',
        'headers',
        'ip_origin',
        'status',
        'data',
        'response',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'ip_origin' => 'string',
        'status' => WebhookLeadStatus::class,
        'data' => 'array',
        'response' => 'array',
        'processed_at' => 'datetime',
    ];
}
