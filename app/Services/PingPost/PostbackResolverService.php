<?php

namespace App\Services\PingPost;

use App\Enums\PostResultStatus;
use App\Models\PostResult;

class PostbackResolverService
{
  /**
   * Resolve a pending postback with the final price from the buyer.
   */
  public function resolvePostback(int $postResultId, float $finalPrice): PostResult
  {
    $postResult = PostResult::with('leadDispatch.postResults')->findOrFail($postResultId);

    $postResult->update([
      'status' => PostResultStatus::POSTBACK_RESOLVED,
      'price_final' => $finalPrice,
      'postback_received_at' => now(),
    ]);

    $dispatch = $postResult->leadDispatch;

    if ($dispatch && !$dispatch->status->isTerminal()) {
      $dispatch->markAsSold($postResult->integration, $finalPrice);
    }

    return $postResult->fresh();
  }

  /**
   * Expire all pending postbacks past their expiry date.
   * If the associated dispatch has no other open items, mark it as not_sold.
   *
   * @return int Number of expired records
   */
  public function expireStalePostbacks(): int
  {
    $stale = PostResult::query()->where('status', PostResultStatus::PENDING_POSTBACK)->where('postback_expires_at', '<', now())->get();

    $count = 0;

    foreach ($stale as $postResult) {
      $postResult->update(['status' => PostResultStatus::SKIPPED]);
      $count++;

      $dispatch = $postResult->leadDispatch;

      if (!$dispatch || $dispatch->status->isTerminal()) {
        continue;
      }

      // Check if all post results for this dispatch are terminal
      $stillPending = $dispatch
        ->postResults()
        ->whereIn('status', ['pending_postback', 'retry_queued'])
        ->exists();

      if (!$stillPending) {
        $dispatch->markAsNotSold();
      }
    }

    return $count;
  }
}
