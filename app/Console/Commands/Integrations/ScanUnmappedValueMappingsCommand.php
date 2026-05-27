<?php

namespace App\Console\Commands\Integrations;

use App\Services\Integrations\UnmappedValueMappingScanner;
use App\Support\SlackMessageBundler;
use Illuminate\Console\Command;
use Maxidev\Logger\TailLogger;

class ScanUnmappedValueMappingsCommand extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'integrations:scan-unmapped-value-mappings {--no-slack : Skip the Slack notification on new findings}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Detect active integrations whose fields with possible_values have no value_mapping configured.';

  /**
   * Execute the console command.
   */
  public function handle(UnmappedValueMappingScanner $scanner): int
  {
    $result = $scanner->scan();

    $summary = "Scan complete: {$result['new_count']} new, {$result['resolved']} resolved, {$result['open']} open finding(s).";
    $this->info($summary);

    // Always leave a trace of the run in the log — this is the visibility in local/non-prod
    // (where Slack is intentionally not fired).
    TailLogger::saveLog($summary, 'scans/detect-unmapped', 'info', [
      'new_count' => $result['new_count'],
      'resolved' => $result['resolved'],
      'open' => $result['open'],
      'new' => $result['new'],
    ]);

    if ($result['new_count'] > 0 && !$this->option('no-slack')) {
      // Slack alerts only go out in production; elsewhere the log above is the trace.
      if (app()->isProduction()) {
        $this->notifySlack($result['new']);
        $this->info('Slack notification sent.');
      } else {
        $this->info('Slack notification skipped (non-production).');
      }
    }

    return self::SUCCESS;
  }

  /**
   * Notify Slack about the newly detected findings.
   *
   * @param  array<int, array{integration_id: int, integration: string, company: string, field: string}>  $new
   * @return bool True when the message was actually sent (webhook configured).
   */
  private function notifySlack(array $new): bool
  {
    $lines = array_map(fn(array $f) => "• *#{$f['integration_id']} {$f['integration']}* · _{$f['company']}_ → `{$f['field']}`", $new);
    $count = count($new);

    return (new SlackMessageBundler())
      ->createAttachment('#f0a500')
      ->addTitle("Integrations: {$count} field(s) need a value mapping", '⚠️')
      ->addSection('These fields have possible values but no value mapping configured — raw values would reach the buyer unmapped.')
      ->addDivider()
      ->addSection(implode("\n", $lines))
      ->closeAttachment()
      ->sendDirect('default');
  }
}
