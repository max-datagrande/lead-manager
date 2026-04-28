<?php

namespace App\Http\Controllers\LeadQuality;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeadQuality\ProviderOtpTestSendRequest;
use App\Http\Requests\LeadQuality\ProviderOtpTestVerifyRequest;
use App\Http\Requests\LeadQuality\ProviderPhoneValidateTestRequest;
use App\Models\LeadQualityProvider;
use App\Services\LeadQuality\LeadQualityProviderResolver;
use App\Services\LeadQuality\OtpProviderTester;
use Illuminate\Http\JsonResponse;
use RuntimeException;

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

  /**
   * Admin-only smoke test for sync phone-validation providers (Melissa). Calls
   * `validatePhone()` directly — bypassing `PhoneValidationService` and its
   * cache so the admin always sees fresh upstream output. The HTTP exchange
   * is recorded with `operation=test_validate_phone` to keep it separate
   * from production traffic.
   */
  public function testValidatePhone(ProviderPhoneValidateTestRequest $request, LeadQualityProvider $provider): JsonResponse
  {
    $data = $request->validated();
    $country = strtoupper((string) ($data['country'] ?? 'US'));

    try {
      $impl = $this->resolver->forPhoneValidation($provider);
    } catch (\InvalidArgumentException | RuntimeException $e) {
      return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
    }

    $result = $impl->validatePhone($provider, (string) $data['phone'], [
      'country' => $country,
      'country_origin' => $country,
      'trace' => 'admin_test',
      'operation' => 'test_validate_phone',
    ]);

    $payload = [
      'ok' => $result->classification !== 'validation_error',
      'valid' => $result->valid,
      'classification' => $result->classification,
      'line_type' => $result->lineType,
      'country' => $result->country,
      'carrier' => $result->carrier,
      'normalized_phone' => $result->normalizedPhone,
      'result_codes' => $result->resultCodes,
      'error' => $result->error,
    ];

    return response()->json($payload, $payload['ok'] ? 200 : 422);
  }
}
