<?php

namespace App\Http\Controllers;

use App\Enums\PostbackSource;
use App\Enums\PostbackType;
use App\Models\Postback;
use App\Models\Workflow;
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
