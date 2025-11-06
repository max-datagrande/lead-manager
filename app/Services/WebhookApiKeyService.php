<?php

namespace App\Services;

use Illuminate\Http\Request;
use Maxidev\Logger\TailLogger;
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
        // Log the result
        TailLogger::saveLog('Facebook Webhook Verification', 'webhooks/leads/store', 'info', [
          'message' => 'Verification successful',
        ]);
        return response($request->input('hub_challenge'), 200);
      }

      return false; // Verification failed
    }

    if ($request->isMethod('post')) {
      $appSecret = config('services.facebook.app_secret');
      $signature = $request->header('X-Hub-Signature-256');

      if (!$appSecret || !$signature) {
        TailLogger::saveLog('Facebook Webhook Verification', 'webhooks/leads/store', 'error', [
          'message' => 'Configuration or signature missing',
        ]);
        return false; // Configuration or signature missing
      }

      // Calculate expected signature
      $hash = hash_hmac('sha256', $request->getContent(), $appSecret);
      $expectedSignature = 'sha256=' . $hash;
      $isEquals = hash_equals($expectedSignature, $signature);
      //Log the result
      TailLogger::saveLog('Facebook Webhook Verification', 'webhooks/leads/store', 'info', [
        'isEquals' => $isEquals,
        'expectedSignature' => $expectedSignature,
        'signature' => $signature,
      ]);
      // Securely compare signatures
      return $isEquals;
    }
    // Log the result
    TailLogger::saveLog('Facebook Webhook Verification', 'webhooks/leads/store', 'error', [
      'message' => 'Invalid signature',
    ]);

    return false; // Invalid signature
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
