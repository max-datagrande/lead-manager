<?php

namespace App\Console\Commands;

use App\Models\Workflow;
use App\Models\WorkflowAlert;
use App\Services\Alerts\AlertChannelResolver;
use Illuminate\Console\Command;

class TestWorkflowAlertCommand extends Command
{
  protected $signature = 'workflow:test-alert {workflow : The workflow ID}';

  protected $description = 'Send a test alert to all active channels linked to a workflow';

  public function handle(AlertChannelResolver $resolver): int
  {
    $workflow = Workflow::find($this->argument('workflow'));

    if (!$workflow) {
      $this->error("Workflow #{$this->argument('workflow')} not found.");
      return self::FAILURE;
    }

    $alerts = WorkflowAlert::query()->where('workflow_id', $workflow->id)->where('is_active', true)->with('alertChannel')->get();

    if ($alerts->isEmpty()) {
      $this->warn("No active alert channels configured for workflow '{$workflow->name}' (#{$workflow->id}).");
      return self::SUCCESS;
    }

    $this->info("Sending test alert to {$alerts->count()} channel(s) for workflow '{$workflow->name}'...");

    $message = "[TEST] Workflow '{$workflow->name}' — This is a test alert to verify channel connectivity.";
    $context = [
      'title' => 'Test Alert — Workflow Dispatch',
      'fields' => [
        'Workflow' => "{$workflow->name} (#{$workflow->id})",
        'Strategy' => $workflow->strategy->value,
        'Type' => 'Test — no real error occurred',
      ],
    ];

    foreach ($alerts as $alert) {
      $channel = $alert->alertChannel;
      try {
        $driver = $resolver->make($channel->type);
        $driver->send($channel->webhook_url, $message, $context);
        $this->line("  ✓ <info>{$channel->name}</info> ({$channel->type}) — sent");
      } catch (\Throwable $e) {
        $this->line("  ✗ <error>{$channel->name}</error> ({$channel->type}) — {$e->getMessage()}");
      }
    }

    $this->newLine();
    $this->info('Done.');
    return self::SUCCESS;
  }
}
