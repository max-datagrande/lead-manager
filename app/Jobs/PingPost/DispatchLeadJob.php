<?php

namespace App\Jobs\PingPost;

use App\Models\Lead;
use App\Models\Workflow;
use App\Services\PingPost\DispatchOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Maxidev\Logger\TailLogger;

class DispatchLeadJob implements ShouldQueue
{
  use Queueable;

  public int $tries = 1;

  public function __construct(
    public readonly int $workflowId,
    public readonly int $leadId,
    public readonly string $fingerprint,
  ) {}

  public function handle(DispatchOrchestrator $orchestrator): void
  {
    TailLogger::saveLog('Job START', 'dispatch/debug', 'info', [
      'workflow_id' => $this->workflowId,
      'lead_id' => $this->leadId,
      'fingerprint' => $this->fingerprint,
    ]);

    $workflow = Workflow::findOrFail($this->workflowId);
    $lead = Lead::findOrFail($this->leadId);

    TailLogger::saveLog('Job models loaded', 'dispatch/debug', 'info', [
      'workflow_strategy' => $workflow->strategy?->value,
      'lead_exists' => (bool) $lead,
    ]);

    $orchestrator->dispatch($workflow, $lead, $this->fingerprint);

    TailLogger::saveLog('Job DONE', 'dispatch/debug');
  }
}
