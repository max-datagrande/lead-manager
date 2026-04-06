<?php

namespace App\Jobs\PingPost;

use App\Enums\PostResultStatus;
use App\Models\PostResult;
use App\Services\PingPost\PostService;
use App\Support\SlackMessageBundler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RetryPostJob implements ShouldQueue
{
  use Queueable;

  public int $tries = 10;

  /** @var array<int, int> */
  public array $backoff = [3, 3, 3, 3, 3, 3, 3, 3, 3, 3];

  public function __construct(public readonly int $postResultId) {}

  public function handle(PostService $postService): void
  {
    $postResult = PostResult::with(['leadDispatch', 'integration', 'integration.buyerConfig', 'pingResult'])->findOrFail($this->postResultId);

    $dispatch = $postResult->leadDispatch;
    $integration = $postResult->integration;
    $config = $integration->buyerConfig;
    $leadData = $dispatch->lead->leadFieldResponses->pluck('value', 'field.name')->toArray();

    $postResult->increment('attempt_count');

    $result = $postService->post($integration, $config, $dispatch, $leadData, $postResult->pingResult, (float) ($postResult->price_offered ?? 0));

    // Update original record to retry_queued to track lineage
    $postResult->update(['status' => PostResultStatus::RETRY_QUEUED]);

    if ($result->status->isTerminal()) {
      // If accepted, mark dispatch as sold
      if ($result->status->isSold()) {
        $dispatch->markAsSold($integration, (float) $result->price_final);
      }
    }
  }

  public function failed(Throwable $exception): void
  {
    $postResult = PostResult::find($this->postResultId);

    if ($postResult) {
      $postResult->update(['status' => PostResultStatus::ERROR]);

      $dispatch = $postResult->leadDispatch;
      if ($dispatch && !$dispatch->status->isTerminal()) {
        $dispatch->markAsError('RetryPostJob exhausted all attempts: ' . $exception->getMessage());
      }
    }

    $slack = new SlackMessageBundler();
    $slack
      ->addTitle('RetryPostJob — All Retries Exhausted', '🚨')
      ->addKeyValue('Post Result ID', $this->postResultId, true)
      ->addKeyValue('Error', $exception->getMessage(), true)
      ->sendDirect('error');
  }
}
