<?php

namespace App\Http\Controllers\LeadQuality;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeadQuality\ProviderOtpTestSendRequest;
use App\Http\Requests\LeadQuality\ProviderOtpTestVerifyRequest;
use App\Models\LeadQualityProvider;
use App\Services\LeadQuality\LeadQualityProviderResolver;
use App\Services\LeadQuality\OtpProviderTester;
use Illuminate\Http\JsonResponse;

class ProviderTestController extends Controller
{
  public function __construct(private readonly LeadQualityProviderResolver $resolver, private readonly OtpProviderTester $otpTester) {}

  public function test(LeadQualityProvider $provider): JsonResponse
  {
    try {
      $service = $this->resolver->forProvider($provider);
    } catch (\InvalidArgumentException $e) {
      return response()->json(
        [
          'ok' => false,
          'error' => $e->getMessage(),
        ],
        422,
      );
    }

    $result = $service->testConnection($provider);

    return response()->json(
      [
        'ok' => $result->ok,
        'message' => $result->message,
        'error' => $result->error,
      ],
      $result->ok ? 200 : 422,
    );
  }

  /**
   * Admin-only smoke test: dispatches an OTP via the provider without
   * creating a LeadDispatch or ValidationLog. Technical request/response
   * is still recorded in `external_service_requests` (operation=test_send).
   */
  public function testSendOtp(ProviderOtpTestSendRequest $request, LeadQualityProvider $provider): JsonResponse
  {
    $data = $request->validated();
    $result = $this->otpTester->testSend(
      $provider,
      (string) $data['to'],
      (string) ($data['channel'] ?? 'sms'),
      isset($data['locale']) ? (string) $data['locale'] : null,
    );

    return response()->json($result, $result['ok'] ? 200 : 422);
  }

  /**
   * Admin-only smoke test: verifies an OTP code against the provider. Same
   * principle as testSendOtp — no business state is written, only the
   * technical request/response row (operation=test_verify).
   */
  public function testVerifyOtp(ProviderOtpTestVerifyRequest $request, LeadQualityProvider $provider): JsonResponse
  {
    $data = $request->validated();
    $result = $this->otpTester->testVerify($provider, (string) $data['to'], (string) $data['code']);

    return response()->json($result, $result['ok'] ? 200 : 422);
  }
}
