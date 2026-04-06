<?php

namespace App\Listeners;

use App\Enums\PostbackSource;
use App\Events\LeadSold;
use App\Services\InternalTokenResolverService;
use App\Services\PostbackFireService;
use App\Support\SlackMessageBundler;
use Illuminate\Support\Facades\Log;
use Maxidev\Logger\TailLogger;
use Throwable;

class FireInternalPostbacksListener
{
  public function __construct(private readonly InternalTokenResolverService $tokenResolver, private readonly PostbackFireService $fireService) {}

  public function handle(LeadSold $event): void
  {
    try {
      $dispatch = $event->dispatch;
      $workflow = $dispatch->workflow;

      TailLogger::saveLog('LeadSold event received', 'postback/internal', 'info', [
        'dispatch_id' => $dispatch->id,
        'dispatch_uuid' => $dispatch->dispatch_uuid,
        'workflow_id' => $workflow?->id,
        'fingerprint' => $dispatch->fingerprint,
      ]);

      if (!$workflow) {
        TailLogger::saveLog('No workflow found, skipping', 'postback/internal', 'warning', [
          'dispatch_id' => $dispatch->id,
        ]);
        return;
      }

      $postbacks = $workflow->postbacks()->internal()->active()->get();

      TailLogger::saveLog('Postbacks query result', 'postback/internal', 'info', [
        'workflow_id' => $workflow->id,
        'postbacks_found' => $postbacks->count(),
        'postback_ids' => $postbacks->pluck('id')->toArray(),
      ]);

      if ($postbacks->isEmpty()) {
        TailLogger::saveLog('No active internal postbacks, skipping', 'postback/internal', 'info', [
          'workflow_id' => $workflow->id,
        ]);
        return;
      }

      $resolvedTokens = $this->tokenResolver->resolveFromFingerprint($dispatch->fingerprint);
      $saleParams = $this->tokenResolver->buildSaleParams($dispatch);
      $params = array_merge($resolvedTokens, $saleParams);

      TailLogger::saveLog('Tokens resolved, firing postbacks', 'postback/internal', 'info', [
        'sale_params' => $saleParams,
        'total_params' => count($params),
      ]);

      foreach ($postbacks as $postback) {
        try {
          $execution = $this->fireService->fireInternal(
            uuid: $postback->uuid,
            params: $params,
            source: PostbackSource::WORKFLOW,
            sourceReference: $dispatch->dispatch_uuid,
          );

          TailLogger::saveLog('Postback fired OK', 'postback/internal', 'info', [
            'postback_id' => $postback->id,
            'postback_name' => $postback->name,
            'execution_id' => $execution->id,
            'status' => $execution->status->value ?? $execution->status,
          ]);
        } catch (Throwable $e) {
          TailLogger::saveLog('Postback fire FAILED', 'postback/internal', 'error', [
            'postback_id' => $postback->id,
            'postback_name' => $postback->name,
            'error' => $e->getMessage(),
          ]);

          Log::error('Failed to fire internal postback for sale', [
            'postback_id' => $postback->id,
            'dispatch_uuid' => $dispatch->dispatch_uuid,
            'error' => $e->getMessage(),
          ]);
        }
      }

      TailLogger::saveLog('All postbacks processed', 'postback/internal', 'info', [
        'dispatch_uuid' => $dispatch->dispatch_uuid,
        'total' => $postbacks->count(),
      ]);
    } catch (Throwable $e) {
      TailLogger::saveLog('Listener FAILED globally', 'postback/internal', 'error', [
        'dispatch_id' => $event->dispatch->id ?? null,
        'error' => $e->getMessage(),
        'file' => $e->getFile() . ':' . $e->getLine(),
      ]);

      Log::error('FireInternalPostbacksListener failed', [
        'dispatch_id' => $event->dispatch->id ?? null,
        'error' => $e->getMessage(),
      ]);

      try {
        $d = $event->dispatch;
        (new SlackMessageBundler())
          ->createAttachment('#f59e0b')
          ->addTitle('Internal Postback Fire Failed', '⚡')
          ->addSection("Listener failed for dispatch *#{$d->id}* (`{$d->dispatch_uuid}`).")
          ->addKeyValue('Error', $e->getMessage(), true, '💥')
          ->closeAttachment()
          ->sendDirect('error');
      } catch (Throwable) {
        // Slack must never break the flow
      }
    }
  }
}
