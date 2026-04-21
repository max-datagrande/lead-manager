<?php

namespace App\Jobs\PingPost;

use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\Workflow;
use App\Services\PingPost\DispatchOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Maxidev\Logger\TailLogger;

class DispatchLeadJob implements ShouldQueue
{
  use Queueable;

  public int $tries = 1;

  /**
   * @param  ?int  $leadDispatchId  When supplied, the orchestrator reuses the existing
   *                                 LeadDispatch instead of creating a new one. Used by
   *                                 Lead Quality's challenge/verify flow, which creates
   *                                 the dispatch in PENDING_VALIDATION state up-front.
   */
  public function __construct(
    public readonly int $workflowId,
    public readonly int $leadId,
    public readonly string $fingerprint,
    public readonly ?int $leadDispatchId = null,
  ) {}

  public function handle(DispatchOrchestrator $orchestrator): void
  {
    TailLogger::saveLog('Job START', 'dispatch/debug', 'info', [
      'workflow_id' => $this->workflowId,
      'lead_id' => $this->leadId,
      'fingerprint' => $this->fingerprint,
      'existing_dispatch_id' => $this->leadDispatchId,
    ]);

    $workflow = Workflow::findOrFail($this->workflowId);
    $lead = Lead::findOrFail($this->leadId);
    $existing = $this->leadDispatchId ? LeadDispatch::find($this->leadDispatchId) : null;

    TailLogger::saveLog('Job models loaded', 'dispatch/debug', 'info', [
      'workflow_strategy' => $workflow->strategy?->value,
      'lead_exists' => (bool) $lead,
      'dispatch_reused' => (bool) $existing,
    ]);

    $orchestrator->dispatch($workflow, $lead, $this->fingerprint, existingDispatch: $existing);

    TailLogger::saveLog('Job DONE', 'dispatch/debug');
  }
}
