<?php

namespace App\Listeners;

use App\Events\LeadWorkflowOverridden;
use App\Models\TrafficLog;
use App\Models\Workflow;
use App\Support\SlackMessageBundler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maxidev\Logger\TailLogger;
use Throwable;

/**
 * Notifies the Slack notify channel (slack-alerts.webhook_urls.default) whenever a
 * lead dispatch was redirected to a different workflow via the URL `?workflow_id`
 * override. Queued so the webhook call never blocks the SDK dispatch request.
 */
class NotifyWorkflowOverride implements ShouldQueue
{
  public function handle(LeadWorkflowOverridden $event): void
  {
    try {
      $intended = Workflow::find($event->idIntended);
      $effective = Workflow::find($event->idEffective);

      $trafficLog = TrafficLog::where('fingerprint', $event->fingerprint)->latest('created_at')->first();

      $landing = $trafficLog ? trim(($trafficLog->host ?? '') . ' ' . ($trafficLog->path_visited ?? '')) : null;
      $s10 = $trafficLog?->s10;

      $bundler = (new SlackMessageBundler())
        ->createAttachment('#f0a500')
        ->addTitle('Lead Workflow Override', '🔀')
        ->addSection("*Intended workflow:* #{$event->idIntended} " . ($intended?->name ?? '_(not found)_'))
        ->addSection("*Effective workflow:* #{$event->idEffective} " . ($effective?->name ?? '_(not found)_'))
        ->addSection('*Landing:* ' . (!empty($landing) ? $landing : '_(unknown)_'))
        ->addSection("*Fingerprint:* `{$event->fingerprint}`");

      if (!empty($s10)) {
        $bundler->addSection("*s10:* `{$s10}`");
      }

      $bundler->closeAttachment()->sendDirect('default');
    } catch (Throwable $e) {
      TailLogger::saveLog('Workflow override Slack notification failed', 'notifications/slack', 'error', [
        'lead_id' => $event->leadId,
        'id_intended' => $event->idIntended,
        'id_effective' => $event->idEffective,
        'error' => $e->getMessage(),
      ]);
    }
  }
}
