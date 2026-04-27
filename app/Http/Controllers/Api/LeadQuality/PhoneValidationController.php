<?php

namespace App\Http\Controllers\Api\LeadQuality;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeadQuality\ValidatePhoneRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Services\LeadQuality\DTO\PhoneValidationResult;
use App\Services\LeadQuality\PhoneValidationService;
use Illuminate\Http\JsonResponse;

/**
 * Public phone-validation endpoint. Called by the landing right before
 * `requestChallenge` (or `shareLead`) to filter fakes/disposables ahead of
 * spending an SMS credit.
 *
 * Response status convention:
 *   - 200 with `valid: true` for any acceptable classification.
 *   - 200 with `valid: false` for hard rejections (invalid/disconnected/disposable).
 *     A rejected phone is a successful business outcome, not a server error.
 *   - 502 only when the upstream itself failed (license issue, timeout, no provider).
 */
class PhoneValidationController extends Controller
{
  use ApiResponseTrait;

  public function __construct(private readonly PhoneValidationService $service) {}

  public function validatePhone(ValidatePhoneRequest $request): JsonResponse
  {
    $data = $request->validated();

    $result = $this->service->validate((string) $data['phone'], null, [
      'country' => (string) ($data['country'] ?? 'US'),
      'trace' => 'fp_' . substr((string) $data['fingerprint'], 0, 32),
    ]);

    if ($result->classification === PhoneValidationResult::CLASS_VALIDATION_ERROR) {
      return $this->errorResponse(
        message: $result->error ?? 'Phone validation upstream failed.',
        errors: ['classification' => $result->classification],
        status: 502,
      );
    }

    return $this->successResponse(
      data: [
        'valid' => $result->valid,
        'classification' => $result->classification,
        'line_type' => $result->lineType,
        'country' => $result->country,
        'carrier' => $result->carrier,
        'normalized_phone' => $result->normalizedPhone,
        'error' => $result->error,
      ],
      message: $result->valid ? 'Phone validated.' : $result->error ?? 'Phone rejected.',
    );
  }
}
