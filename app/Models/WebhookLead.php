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
        'status',
        'data',
        'response',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'status' => WebhookLeadStatus::class,
        'data' => 'array',
        'response' => 'array',
        'processed_at' => 'datetime',
    ];
}
