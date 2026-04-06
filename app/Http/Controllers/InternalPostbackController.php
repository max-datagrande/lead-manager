<?php

namespace App\Http\Controllers;

use App\Enums\PostbackSource;
use App\Models\Postback;
use App\Services\InternalTokenResolverService;
use App\Services\PostbackFireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InternalPostbackController extends Controller
{
  public function __construct(
    protected PostbackFireService $fireService,
    protected InternalTokenResolverService $tokenResolver,
  ) {}

  public function fireForm(Postback $postback): Response
  {
    return Inertia::render('postbacks/internal-fire', [
      'postback' => $postback,
    ]);
  }

  public function resolveTokens(Request $request): JsonResponse
  {
    $request->validate(['fingerprint' => ['required', 'string', 'max:100']]);

    $values = $this->tokenResolver->resolveFromFingerprint($request->input('fingerprint'));

    return response()->json(['success' => true, 'data' => $values]);
  }

  public function fire(Request $request, Postback $postback): RedirectResponse
  {
    $params = $request->except('_token');

    try {
      $execution = $this->fireService->fireInternal(
        uuid: $postback->uuid,
        params: $params,
        source: PostbackSource::MANUAL,
      );

      add_flash_message(
        type: 'success',
        message: "Postback fired. Execution: {$execution->execution_uuid} (status: {$execution->status->value})",
      );
    } catch (\Throwable $e) {
      add_flash_message(type: 'error', message: "Fire failed: {$e->getMessage()}");
    }

    return redirect()->route('postbacks.index');
  }
}
