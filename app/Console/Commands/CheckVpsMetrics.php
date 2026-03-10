<?php

namespace App\Console\Commands;

use App\Services\HostingerVpsService;
use App\Support\SlackMessageBundler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckVpsMetrics extends Command
{
    protected $signature = 'vps:check-metrics {--test : Force send Slack alert regardless of CPU threshold}';

    protected $description = 'Check VPS CPU usage and alert Slack if above threshold';

    private const CPU_THRESHOLD = 60.0;

    public function __construct(private HostingerVpsService $vpsService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Fetching VPS metrics from Hostinger API...');

        $this->vpsService->flush();
        $metrics = $this->vpsService->getMetrics();

        if ($metrics === null) {
            $this->error('Failed to retrieve VPS metrics. Check API token and connectivity.');
            Log::warning('CheckVpsMetrics: could not retrieve VPS metrics from Hostinger API');
            return self::FAILURE;
        }

        $cpu  = $metrics['current_cpu'];
        $ram  = number_format($metrics['current_ram'] / 1024 ** 3, 1) . ' GB';
        $disk = number_format($metrics['disk_bytes'] / 1024 ** 3, 1) . ' GB';

        $this->table(
            ['Metric', 'Value'],
            [
                ['CPU', "{$cpu}%"],
                ['RAM', $ram],
                ['Disk', $disk],
            ]
        );

        $forceTest = $this->option('test');

        if (! $forceTest && $cpu < self::CPU_THRESHOLD) {
            $this->info("CPU is within safe limits ({$cpu}% < " . self::CPU_THRESHOLD . "%). No alert sent.");
            return self::SUCCESS;
        }

        if ($forceTest) {
            $this->warn('[TEST] Forcing Slack alert regardless of CPU threshold...');
        } else {
            $this->warn("CPU above threshold ({$cpu}% >= " . self::CPU_THRESHOLD . "%). Sending Slack alert...");
        }

        [$color, $emoji, $level] = match (true) {
            $cpu >= 80.0 => ['#e01e5a', '🚨', 'CRITICAL'],
            $cpu >= 60.0 => ['#f0a500', '⚠️',  'WARNING'],
            default      => ['#36a64f', '✅',  'OK'],
        };

        $slack = new SlackMessageBundler();
        $slack->createAttachment($color)
              ->addTitle("VPS CPU {$level}", $emoji)
              ->addSection("CPU usage is *{$cpu}%* (limit: " . self::CPU_THRESHOLD . "%)")
              ->addDivider()
              ->addKeyValue('RAM', $ram)
              ->addKeyValue('Disk', $disk)
              ->closeAttachment()
              ->sendDirect('default');

        $this->warn('Slack alert sent to default channel.');

        return self::SUCCESS;
    }
}
