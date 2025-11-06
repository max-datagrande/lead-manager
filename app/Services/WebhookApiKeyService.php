<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookApiKeyService
{
  /**
   * Validate the incoming webhook request based on its source.
   *
   * @param string $source
   * @param Request $request
   * @return bool|Response
   */
  public function validate(string $source, Request $request): bool|Response
  {
    switch ($source) {
      case 'facebook':
        return $this->handleFacebook($request);
      default:
        return $this->handleGeneric($request);
    }
  }

  /**
   * Handle the Facebook webhook verification (GET) and data (POST).
   */
  private function handleFacebook(Request $request): bool|Response
  {

    if ($request->isMethod('get')) {
      $verifyToken = config('auth.webhooks.api_key');
      if (
        $request->input('hub_mode') === 'subscribe' &&
        $request->input('hub_verify_token') === $verifyToken
      ) {
        // Respond directly with the challenge code as required by Facebook.
        return response($request->input('hub_challenge'), 200);
      }

      // If verification fails, deny access.
      return false;
    }

    // For POST requests, for now, we allow them to pass through for logging.
    // Later, we will add X-Hub-Signature-256 validation here.
    return true;
  }

  /**
   * Handle the generic API key validation.
   */
  private function handleGeneric(Request $request): bool
  {
    $apiKey = $request->header('X-API-KEY');
    $validApiKey = config('auth.webhooks.api_key');

    if (!$validApiKey || !$apiKey || !hash_equals($validApiKey, $apiKey)) {
      return false;
    }

    return true;
  }
}
