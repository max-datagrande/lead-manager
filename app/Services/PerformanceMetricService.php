<?php

namespace App\Services;

use App\Models\PerformanceMetric;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PerformanceMetricService
{
    /**
     * Records a raw performance metric and upserts the daily aggregate.
     *
     * @param array{host: string, load_time_ms: int, fingerprint?: string|null, device_type?: string|null} $data
     */
    public function record(array $data): void
    {
        $host = $data['host'];
        $loadTimeMs = $data['load_time_ms'];

        // 1. Insert raw metric
        PerformanceMetric::create([
            'id' => (string) Str::uuid(),
            'fingerprint' => $data['fingerprint'] ?? null,
            'host' => $host,
            'load_time_ms' => $loadTimeMs,
            'device_type' => $data['device_type'] ?? null,
            'recorded_at' => now()->toDateString(),
        ]);

        // 2. Upsert daily aggregate
        DB::statement("
            INSERT INTO performance_metrics_daily (id, host, recorded_date, request_count, total_ms, avg_ms, min_ms, max_ms, created_at, updated_at)
            VALUES (?, ?, CURRENT_DATE, 1, ?, ?, ?, ?, NOW(), NOW())
            ON CONFLICT (host, recorded_date) DO UPDATE SET
                request_count = performance_metrics_daily.request_count + 1,
                total_ms = performance_metrics_daily.total_ms + EXCLUDED.total_ms,
                avg_ms = (performance_metrics_daily.total_ms + EXCLUDED.total_ms)::numeric / (performance_metrics_daily.request_count + 1),
                min_ms = LEAST(performance_metrics_daily.min_ms, EXCLUDED.min_ms),
                max_ms = GREATEST(performance_metrics_daily.max_ms, EXCLUDED.max_ms),
                updated_at = NOW()
        ", [
            (string) Str::uuid(),
            $host,
            $loadTimeMs,
            $loadTimeMs,
            $loadTimeMs,
            $loadTimeMs,
        ]);
    }

    /**
     * Dashboard summary: daily average load time for the last N days.
     *
     * @return array<int, object{recorded_date: string, total_requests: int, avg_ms: float}>
     */
    public function getDashboardSummary(int $days = 30): array
    {
        return DB::table('performance_metrics_daily')
            ->where('recorded_date', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('recorded_date, SUM(request_count) as total_requests, ROUND(SUM(total_ms)::numeric / NULLIF(SUM(request_count), 0), 2) as avg_ms')
            ->groupBy('recorded_date')
            ->orderBy('recorded_date')
            ->get()
            ->toArray();
    }

    /**
     * Per-host metrics with optional filters.
     *
     * @return array<int, object>
     */
    public function getHostMetrics(?string $host = null, ?string $from = null, ?string $to = null): array
    {
        return DB::table('performance_metrics_daily')
            ->when($host, fn ($q) => $q->where('host', $host))
            ->when($from, fn ($q) => $q->where('recorded_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('recorded_date', '<=', $to))
            ->orderBy('recorded_date')
            ->get()
            ->toArray();
    }

    /**
     * Unique hosts that have recorded metrics.
     *
     * @return string[]
     */
    public function getHosts(): array
    {
        return DB::table('performance_metrics_daily')
            ->distinct()
            ->pluck('host')
            ->toArray();
    }

    /**
     * Aggregated stats for a given period (used in detail page summary cards).
     *
     * @return object{avg_ms: float, min_ms: int, max_ms: int, total_requests: int}|null
     */
    public function getPeriodStats(?string $host = null, ?string $from = null, ?string $to = null): ?object
    {
        return DB::table('performance_metrics_daily')
            ->when($host, fn ($q) => $q->where('host', $host))
            ->when($from, fn ($q) => $q->where('recorded_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('recorded_date', '<=', $to))
            ->selectRaw('
                ROUND(SUM(total_ms)::numeric / NULLIF(SUM(request_count), 0), 2) as avg_ms,
                MIN(min_ms) as min_ms,
                MAX(max_ms) as max_ms,
                SUM(request_count) as total_requests
            ')
            ->first();
    }
}
