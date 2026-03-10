<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceMetricDaily extends Model
{
    protected $table = 'performance_metrics_daily';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'host',
        'recorded_date',
        'request_count',
        'total_ms',
        'avg_ms',
        'min_ms',
        'max_ms',
    ];

    protected $casts = [
        'recorded_date' => 'date',
        'request_count' => 'integer',
        'total_ms' => 'integer',
        'avg_ms' => 'decimal:2',
        'min_ms' => 'integer',
        'max_ms' => 'integer',
    ];
}
