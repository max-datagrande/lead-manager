<?php

namespace App\Listeners;

use App\Enums\PostbackSource;
use App\Events\LeadSold;
use App\Services\InternalTokenResolverService;
use App\Services\PostbackFireService;
use Illuminate\Support\Facades\Log;
use Throwable;

class FireInternalPostbacksListener
{
  public function __construct(private readonly InternalTokenResolverService $tokenResolver, private readonly PostbackFireService $fireService) {}

  public function handle(LeadSold $event): void
  {
    try {
      $dispatch = $event->dispatch;
      $workflow = $dispatch->workflow;

      if (!$workflow) {
        return;
      }

      $postbacks = $workflow->postbacks()->internal()->active()->get();

      if ($postbacks->isEmpty()) {
        return;
      }

      $resolvedTokens = $this->tokenResolver->resolveFromFingerprint($dispatch->fingerprint);

      $saleParams = $this->tokenResolver->buildSaleParams($dispatch);

      $params = array_merge($resolvedTokens, $saleParams);

      foreach ($postbacks as $postback) {
        try {
          $this->fireService->fireInternal(
            uuid: $postback->uuid,
            params: $params,
            source: PostbackSource::WORKFLOW,
            sourceReference: $dispatch->dispatch_uuid,
          );
        } catch (Throwable $e) {
          Log::error('Failed to fire internal postback for sale', [
            'postback_id' => $postback->id,
            'dispatch_uuid' => $dispatch->dispatch_uuid,
            'error' => $e->getMessage(),
          ]);
        }
      }
    } catch (Throwable $e) {
      Log::error('FireInternalPostbacksListener failed', [
        'dispatch_id' => $event->dispatch->id ?? null,
        'error' => $e->getMessage(),
      ]);
    }
  }
}
