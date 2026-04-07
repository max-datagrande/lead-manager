<?php

namespace App\Http\Controllers\Api\PingPost;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Integration;
use App\Models\LeadDispatch;
use App\Models\PostResult;
use App\Services\PingPost\PostbackResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostbackWebhookController extends Controller
{
  use ApiResponseTrait;

  public function __construct(private readonly PostbackResolverService $resolver) {}

  public function receive(Request $request, LeadDispatch $dispatch, Integration $integration): JsonResponse
  {
    $postResult = PostResult::query()
      ->where('lead_dispatch_id', $dispatch->id)
      ->where('integration_id', $integration->id)
      ->where('status', 'pending_postback')
      ->first();

    if (!$postResult) {
      return $this->errorResponse('No pending postback found for this dispatch and integration.', null, 404);
    }

    $finalPrice = (float) $request->input('price', $request->input('amount', 0));

    $resolved = $this->resolver->resolvePostback($postResult->id, $finalPrice);

    return $this->successResponse(['status' => $resolved->status->value, 'price_final' => $resolved->price_final], 'Postback received.');
  }
}
