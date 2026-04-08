<?php

namespace App\Jobs\PingPost;

use App\Models\WorkflowAlert;
use App\Services\Alerts\AlertChannelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWorkflowAlertJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public int $tries = 2;

  public function __construct(private readonly int $workflowId, private readonly string $message, private readonly array $context = []) {}

  public function handle(AlertChannelResolver $resolver): void
  {
    $alerts = WorkflowAlert::query()->where('workflow_id', $this->workflowId)->where('is_active', true)->with('alertChannel')->get();

    foreach ($alerts as $alert) {
      try {
        $driver = $resolver->make($alert->alertChannel->type);
        $driver->send($alert->alertChannel->webhook_url, $this->message, $this->context);
      } catch (\Throwable $e) {
        Log::error('Failed to send workflow alert', [
          'workflow_id' => $this->workflowId,
          'alert_channel_id' => $alert->alert_channel_id,
          'driver' => $alert->alertChannel->type,
          'error' => $e->getMessage(),
        ]);
      }
    }
  }
}
