<?php

namespace App\Http\Controllers;

use App\Enums\DispatchStatus;
use App\Enums\PostbackSource;
use App\Enums\PostbackType;
use App\Models\LeadDispatch;
use App\Models\Postback;
use App\Models\PostbackExecution;
use App\Models\Workflow;
use App\Services\InternalTokenResolverService;
use App\Services\PostbackFireService;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostbackAssociationController extends Controller
{
  /**
   * Attach a postback to an entity identified by source + source_id.
   */
  public function store(Request $request): JsonResponse
  {
    $data = $request->validate([
      'source' => ['required', 'string'],
      'source_id' => ['required', 'integer'],
      'postback_id' => ['required', 'integer', 'exists:postbacks,id'],
    ]);

    $source = PostbackSource::from($data['source']);
    $relation = $this->resolveRelation($source, $data['source_id']);

    $postback = Postback::query()->where('id', $data['postback_id'])->where('type', PostbackType::INTERNAL)->firstOrFail();

    if (!$relation->where('postbacks.id', $postback->id)->exists()) {
      $relation->attach($postback->id);
    }

    return response()->json(['success' => true, 'message' => 'Postback associated.']);
  }

  /**
   * Detach a postback from an entity identified by source + source_id.
   */
  public function destroy(Request $request, string $source, int $sourceId, int $postbackId): JsonResponse
  {
    $sourceEnum = PostbackSource::from($source);
    $relation = $this->resolveRelation($sourceEnum, $sourceId);

    $relation->detach($postbackId);

    return response()->json(['success' => true, 'message' => 'Postback detached.']);
  }

  /**
   * Fire internal postbacks for a sold dispatch that missed them.
   */
  public function fireForDispatch(Request $request, InternalTokenResolverService $tokenResolver, PostbackFireService $fireService): JsonResponse
  {
    $data = $request->validate([
      'dispatch_id' => ['required', 'integer', 'exists:lead_dispatches,id'],
    ]);

    $dispatch = LeadDispatch::with(['workflow.postbacks', 'winnerIntegration'])->findOrFail($data['dispatch_id']);

    if ($dispatch->status !== DispatchStatus::SOLD) {
      return response()->json(['success' => false, 'message' => 'Dispatch is not in SOLD status.'], 422);
    }

    $alreadyFired = PostbackExecution::query()->where('source_reference', $dispatch->dispatch_uuid)->exists();

    if ($alreadyFired) {
      return response()->json(['success' => false, 'message' => 'Postbacks already fired for this dispatch.'], 422);
    }

    $postbacks = $dispatch->workflow->postbacks()->internal()->active()->get();

    if ($postbacks->isEmpty()) {
      return response()->json(['success' => false, 'message' => 'No internal postbacks associated with this workflow.'], 422);
    }

    $resolvedTokens = $tokenResolver->resolveFromFingerprint($dispatch->fingerprint);

    $saleParams = [
      'lead_price' => (string) $dispatch->final_price,
      'event_name' => 'sale',
      'buyer_name' => $dispatch->winnerIntegration?->name ?? '',
    ];

    $params = array_merge($resolvedTokens, $saleParams);
    $fired = 0;

    foreach ($postbacks as $postback) {
      try {
        $fireService->fireInternal(
          uuid: $postback->uuid,
          params: $params,
          source: PostbackSource::WORKFLOW,
          sourceReference: $dispatch->dispatch_uuid,
        );
        $fired++;
      } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Failed to fire postback for dispatch', [
          'postback_id' => $postback->id,
          'dispatch_uuid' => $dispatch->dispatch_uuid,
          'error' => $e->getMessage(),
        ]);
      }
    }

    return response()->json(['success' => true, 'fired' => $fired, 'message' => "{$fired} postback(s) fired."]);
  }

  /**
   * Resolve the source enum to the owning model's postbacks relationship.
   */
  private function resolveRelation(PostbackSource $source, int $sourceId): BelongsToMany
  {
    return match ($source) {
      PostbackSource::WORKFLOW => Workflow::findOrFail($sourceId)->postbacks(),
      default => throw new \InvalidArgumentException("Source '{$source->value}' does not support postback associations."),
    };
  }
}
