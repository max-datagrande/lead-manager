<?php

namespace App\Jobs\PingPost;

use App\Models\Lead;
use App\Models\Workflow;
use App\Services\PingPost\DispatchOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
        $workflow = Workflow::findOrFail($this->workflowId);
        $lead = Lead::findOrFail($this->leadId);

        $orchestrator->dispatch($workflow, $lead, $this->fingerprint);
    }
}
