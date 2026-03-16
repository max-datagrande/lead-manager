<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceMetric extends Model
{
    protected $table = 'performance_metrics';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'fingerprint',
        'host',
        'load_time_ms',
        'device_type',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'date',
        'load_time_ms' => 'integer',
    ];
}
