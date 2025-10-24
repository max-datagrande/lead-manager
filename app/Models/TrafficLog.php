<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrafficLog extends Model
{
    use HasFactory;
    protected $table = 'traffic_logs';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'fingerprint',
        'visit_date',
        'visit_count',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'os',
        'referrer',
        'host',
        'path_visited',
        'query_params',
        's1',
        's2',
        's3',
        's4',
        'utm_source',
        'utm_medium',
        'campaign_code',
        'utm_campaign_id',
        'utm_campaign_name',
        'utm_term',
        'utm_content',
        'click_id',
        'country_code',
        'state',
        'city',
        'postal_code',
        'is_bot',
    ];

    protected $casts = [
        'query_params' => 'array',
        'visit_date' => 'date',
        'is_bot' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
