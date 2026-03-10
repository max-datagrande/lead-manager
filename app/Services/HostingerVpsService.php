<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HostingerVpsService
{
    private const CACHE_KEY = 'vps_metrics';
    private const CACHE_TTL = 300; // 5 minutes

    private string $baseUrl = 'https://developers.hostinger.com/api/vps/v1';
    private string $vmId;
    private string $bearerToken;

    public function __construct()
    {
        $this->vmId = config('app.vps.admin.id', '652988');
        $this->bearerToken = config('app.vps.admin.bearer_token', '');
    }

    /**
     * Get VPS metrics, served from cache when available.
     *
     * @return array{current_cpu: float, current_ram: int, disk_bytes: int, sparkline: array<int, array{time: int, cpu: float}>}|null
     */
    public function getMetrics(): ?array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->fetchFromApi());
    }

    /**
     * Invalidate the cached metrics.
     */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Call the Hostinger API and parse the response.
     *
     * @return array|null
     */
    private function fetchFromApi(): ?array
    {
        $now = Carbon::now('UTC');
        $from = $now->copy()->subHours(2)->toIso8601ZuluString();
        $to = $now->toIso8601ZuluString();

        try {
            $response = Http::timeout(15)
                ->withToken($this->bearerToken)
                ->get("{$this->baseUrl}/virtual-machines/{$this->vmId}/metrics", [
                    'date_from' => $from,
                    'date_to'   => $to,
                ]);

            if (! $response->successful()) {
                Log::warning('HostingerVpsService: API request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $this->parse($response->json());
        } catch (\Throwable $e) {
            Log::error('HostingerVpsService: Exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Parse the raw API response into a simplified shape.
     *
     * @param array $data
     * @return array{current_cpu: float, current_ram: int, disk_bytes: int, sparkline: array<int, array{time: int, cpu: float}>}
     */
    private function parse(array $data): array
    {
        $cpuUsage  = $data['cpu_usage']['usage'] ?? [];
        $ramUsage  = $data['ram_usage']['usage'] ?? [];
        $diskSpace = $data['disk_space']['usage'] ?? [];

        // Sort by timestamp ascending
        ksort($cpuUsage);

        $sparkline = [];
        foreach ($cpuUsage as $ts => $value) {
            $sparkline[] = ['time' => (int) $ts, 'cpu' => (float) $value];
        }

        $latestTs   = $sparkline ? $sparkline[array_key_last($sparkline)]['time'] : null;
        $currentCpu = $latestTs ? (float) ($cpuUsage[$latestTs] ?? 0) : 0.0;
        $currentRam = $latestTs ? (int) ($ramUsage[(string) $latestTs] ?? 0) : 0;
        $diskBytes  = $latestTs ? (int) ($diskSpace[(string) $latestTs] ?? 0) : 0;

        return [
            'current_cpu' => $currentCpu,
            'current_ram' => $currentRam,
            'disk_bytes'  => $diskBytes,
            'sparkline'   => $sparkline,
        ];
    }
}
