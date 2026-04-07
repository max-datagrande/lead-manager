<?php

namespace App\Jobs\PingPost;

use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\Workflow;
use App\Services\PingPost\DispatchOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Maxidev\Logger\TailLogger;

class RetryDispatchJob implements ShouldQueue
{
  use Queueable;

  public int $tries = 1;

  public function __construct(public readonly int $dispatchId) {}

  public function handle(DispatchOrchestrator $orchestrator): void
  {
    $original = LeadDispatch::findOrFail($this->dispatchId);
    $rootId = $original->parent_dispatch_id ?? $original->id;

    $maxAttempt =
      LeadDispatch::query()
        ->where(function ($q) use ($rootId) {
          $q->where('id', $rootId)->orWhere('parent_dispatch_id', $rootId);
        })
        ->max('attempt') ?? 1;

    $attempt = $maxAttempt + 1;
    $retryFingerprint = $original->fingerprint . ':retry:' . $attempt;

    TailLogger::saveLog('RetryDispatchJob START', 'dispatch/debug', 'info', [
      'original_dispatch_id' => $original->id,
      'attempt' => $attempt,
      'retry_fingerprint' => $retryFingerprint,
    ]);

    $workflow = Workflow::findOrFail($original->workflow_id);
    $lead = Lead::findOrFail($original->lead_id);

    $orchestrator->dispatch($workflow, $lead, $retryFingerprint, [
      'attempt' => $attempt,
      'parent_dispatch_id' => $rootId,
    ]);

    TailLogger::saveLog('RetryDispatchJob DONE', 'dispatch/debug');
  }
}
