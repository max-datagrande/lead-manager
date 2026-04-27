<?php

namespace App\Services\LeadQuality\Providers;

use App\Exceptions\LeadQuality\ProviderNotEnabledException;
use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use App\Services\ExternalServiceRequest\ExternalRequestRecorder;
use App\Services\LeadQuality\Contracts\LeadQualityProviderInterface;
use App\Services\LeadQuality\Contracts\PhoneValidationProviderInterface;
use App\Services\LeadQuality\DTO\ChallengeResult;
use App\Services\LeadQuality\DTO\PhoneValidationResult;
use App\Services\LeadQuality\DTO\TestConnectionResult;
use App\Services\LeadQuality\DTO\VerifyResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Melissa Global Phone v4 implementation. Sync-only — `validatePhone()` is the
 * primary entry point. Also implements `LeadQualityProviderInterface` so the
 * existing admin resolver can route `testConnection` uniformly across all
 * provider types; the async methods are not supported and will throw.
 */
class MelissaProvider implements PhoneValidationProviderInterface, LeadQualityProviderInterface
{
  private const API_BASE = 'https://globalphone.melissadata.net/V4/WEB/GlobalPhone/doGlobalPhone';

  // Public Melissa example number (guide §10) — used for testConnection only.
  private const TEST_NUMBER = '8006354772';

  private const HTTP_TIMEOUT_SECONDS = 8;

  public function __construct(private readonly ExternalRequestRecorder $recorder) {}

  public function validatePhone(LeadQualityProvider $provider, string $phone, array $context = []): PhoneValidationResult
  {
    $licenseKey = $this->licenseKey($provider);
    if ($licenseKey === null) {
      return PhoneValidationResult::technicalError('Missing Melissa license key.');
    }

    $phone = trim($phone);
    if ($phone === '') {
      return PhoneValidationResult::invalid(classification: PhoneValidationResult::CLASS_INVALID_PHONE, error: 'Empty phone number.');
    }

    $query = $this->buildQuery($provider, $licenseKey, $phone, $context);
    $traceUrl = $this->maskedUrl($query);

    try {
      $response = $this->recorder->record(fn(): Response => Http::acceptJson()->timeout(self::HTTP_TIMEOUT_SECONDS)->get(self::API_BASE, $query), [
        'module' => 'lead_quality',
        'service_name' => 'melissa',
        'service_id' => $provider->id,
        // Caller can override (e.g. admin tester sets 'test_validate_phone'
        // to keep its smoke-test traffic distinguishable in the logs).
        'operation' => (string) ($context['operation'] ?? 'phone_validate'),
        'request_method' => 'GET',
        'request_url' => $traceUrl,
        'request_headers' => ['Accept' => 'application/json'],
      ]);
    } catch (ConnectionException $e) {
      return PhoneValidationResult::technicalError('Connection to Melissa timed out.');
    } catch (Throwable $e) {
      return PhoneValidationResult::technicalError($e->getMessage());
    }

    return $this->interpret($response);
  }

  public function sendChallenge(
    LeadQualityProvider $provider,
    LeadQualityValidationRule $rule,
    LeadQualityValidationLog $log,
    array $context,
  ): ChallengeResult {
    throw ProviderNotEnabledException::forType('melissa');
  }

  public function verifyChallenge(
    LeadQualityProvider $provider,
    LeadQualityValidationRule $rule,
    LeadQualityValidationLog $log,
    string $code,
    array $context,
  ): VerifyResult {
    throw ProviderNotEnabledException::forType('melissa');
  }

  public function testConnection(LeadQualityProvider $provider): TestConnectionResult
  {
    $licenseKey = $this->licenseKey($provider);
    if ($licenseKey === null) {
      return TestConnectionResult::failure('Provider is missing required Melissa credentials (license_key).');
    }

    $query = $this->buildQuery($provider, $licenseKey, self::TEST_NUMBER, ['country' => 'US', 'country_origin' => 'US']);
    $traceUrl = $this->maskedUrl($query);

    try {
      $response = $this->recorder->record(fn(): Response => Http::acceptJson()->timeout(self::HTTP_TIMEOUT_SECONDS)->get(self::API_BASE, $query), [
        'module' => 'lead_quality',
        'service_name' => 'melissa',
        'service_id' => $provider->id,
        'operation' => 'test_connection',
        'request_method' => 'GET',
        'request_url' => $traceUrl,
        'request_headers' => ['Accept' => 'application/json'],
      ]);
    } catch (ConnectionException $e) {
      return TestConnectionResult::failure('Connection to Melissa timed out.');
    } catch (Throwable $e) {
      return TestConnectionResult::failure($e->getMessage());
    }

    $json = $response->json() ?? [];

    if (!$response->successful()) {
      return TestConnectionResult::failure("Melissa responded HTTP {$response->status()}", $json);
    }

    $serviceCodes = $this->parseCodes($json['TransmissionResults'] ?? '');
    if ($serviceErr = $this->serviceErrorMessage($serviceCodes)) {
      return TestConnectionResult::failure($serviceErr, $json);
    }

    $recordCodes = $this->parseCodes($json['Records'][0]['Results'] ?? '');
    if ($recordCodes === []) {
      return TestConnectionResult::failure('Melissa returned an empty record (no result codes).', $json);
    }

    $codeList = implode(',', $recordCodes);
    return TestConnectionResult::success("Connected to Melissa Global Phone. Test number returned: {$codeList}", $json);
  }

  /**
   * Build the Melissa query string for a single-record GET request.
   *
   * @param  array<string, mixed>  $context
   * @return array<string, string>
   */
  private function buildQuery(LeadQualityProvider $provider, string $licenseKey, string $phone, array $context): array
  {
    $settings = $provider->settings ?? [];
    $verifyMode = (string) ($settings['verify_mode'] ?? 'Premium');
    $timeToWait = (string) ($settings['time_to_wait'] ?? '5');
    $callerId = !empty($settings['caller_id']);

    $opts = ["VerifyPhone:{$verifyMode}", "TimeToWait:{$timeToWait}"];
    if ($callerId) {
      $opts[] = 'CallerID:True';
    }

    return [
      'id' => $licenseKey,
      'phone' => $phone,
      't' => (string) ($context['trace'] ?? Str::uuid()->toString()),
      'opt' => implode(',', $opts),
      'ctry' => (string) ($context['country'] ?? 'US'),
      'ctryOrg' => (string) ($context['country_origin'] ?? ($context['country'] ?? 'US')),
    ];
  }

  private function interpret(Response $response): PhoneValidationResult
  {
    $json = $response->json() ?? [];

    if (!$response->successful()) {
      return PhoneValidationResult::technicalError("Melissa responded HTTP {$response->status()}", $json);
    }

    $serviceCodes = $this->parseCodes($json['TransmissionResults'] ?? '');
    if ($serviceErr = $this->serviceErrorMessage($serviceCodes)) {
      return PhoneValidationResult::technicalError($serviceErr, $json);
    }

    $record = $json['Records'][0] ?? null;
    if (!is_array($record)) {
      return PhoneValidationResult::technicalError('Melissa returned no records.', $json);
    }

    $recordCodes = $this->parseCodes($record['Results'] ?? '');
    $classification = $this->classifyResults($recordCodes);
    $lineType = $this->extractLineType($recordCodes);

    $args = [
      'lineType' => $lineType,
      'country' => $record['CountryAbbreviation'] ?? null,
      'carrier' => !empty($record['Carrier']) ? (string) $record['Carrier'] : null,
      'normalizedPhone' => !empty($record['PhoneNumber']) ? (string) $record['PhoneNumber'] : null,
      'resultCodes' => $recordCodes,
      'raw' => $json,
    ];

    if ($this->isValidClassification($classification)) {
      return PhoneValidationResult::valid($classification, ...$args);
    }

    return PhoneValidationResult::invalid($classification, $this->classificationErrorMessage($classification, $recordCodes), ...$args);
  }

  /**
   * Map Melissa record codes into a business classification (matrix from guide §19).
   *
   * @param  array<int, string>  $codes
   */
  private function classifyResults(array $codes): string
  {
    if ($codes === []) {
      return PhoneValidationResult::CLASS_VALIDATION_ERROR;
    }

    if (in_array('PE11', $codes, true)) {
      return PhoneValidationResult::CLASS_DISCONNECTED_PHONE;
    }

    if (in_array('PS19', $codes, true)) {
      return PhoneValidationResult::CLASS_HIGH_RISK_PHONE;
    }

    if (in_array('PE01', $codes, true) || in_array('PE02', $codes, true) || in_array('PE03', $codes, true)) {
      return PhoneValidationResult::CLASS_INVALID_PHONE;
    }

    if (in_array('PS18', $codes, true)) {
      return PhoneValidationResult::CLASS_COMPLIANCE_RISK;
    }

    if (in_array('PS22', $codes, true)) {
      return PhoneValidationResult::CLASS_VALID_HIGH_CONFIDENCE;
    }

    if (in_array('PS01', $codes, true) && in_array('PS20', $codes, true)) {
      return PhoneValidationResult::CLASS_VALID_LOW_CONFIDENCE;
    }

    if (in_array('PS01', $codes, true)) {
      return PhoneValidationResult::CLASS_VALID_LOW_CONFIDENCE;
    }

    if (in_array('PS20', $codes, true)) {
      return PhoneValidationResult::CLASS_LOW_CONFIDENCE;
    }

    if (in_array('PS30', $codes, true)) {
      return PhoneValidationResult::CLASS_PENDING_OR_TIMEOUT;
    }

    return PhoneValidationResult::CLASS_VALIDATION_ERROR;
  }

  /**
   * Treat compliance/low-confidence as valid for the boolean flag — the landing
   * gets the classification too and can decide finer policy. Hard rejects only.
   */
  private function isValidClassification(string $classification): bool
  {
    return match ($classification) {
      PhoneValidationResult::CLASS_VALID_HIGH_CONFIDENCE,
      PhoneValidationResult::CLASS_VALID_LOW_CONFIDENCE,
      PhoneValidationResult::CLASS_LOW_CONFIDENCE,
      PhoneValidationResult::CLASS_COMPLIANCE_RISK,
      PhoneValidationResult::CLASS_PENDING_OR_TIMEOUT
        => true,
      default => false,
    };
  }

  /**
   * @param  array<int, string>  $codes
   */
  private function extractLineType(array $codes): ?string
  {
    if (in_array('PS07', $codes, true)) {
      return 'cellular';
    }
    if (in_array('PS08', $codes, true)) {
      return 'landline';
    }
    if (in_array('PS09', $codes, true)) {
      return 'voip';
    }

    return null;
  }

  /**
   * @param  array<int, string>  $codes
   */
  private function classificationErrorMessage(string $classification, array $codes): string
  {
    return match ($classification) {
      PhoneValidationResult::CLASS_INVALID_PHONE => 'Invalid or malformed phone number.',
      PhoneValidationResult::CLASS_DISCONNECTED_PHONE => 'Phone number has been disconnected.',
      PhoneValidationResult::CLASS_HIGH_RISK_PHONE => 'Phone number flagged as disposable / high risk.',
      default => 'Phone validation failed (codes: ' . implode(',', $codes) . ').',
    };
  }

  /**
   * @param  array<int, string>  $serviceCodes
   */
  private function serviceErrorMessage(array $serviceCodes): ?string
  {
    foreach ($serviceCodes as $code) {
      if (str_starts_with($code, 'GE') || str_starts_with($code, 'SE')) {
        return match ($code) {
          'GE05' => 'Invalid Melissa license key.',
          'GE06', 'GE10' => 'Melissa license is disabled.',
          'GE08' => 'Melissa license does not include phone validation.',
          'GE14' => 'Melissa account is out of credits.',
          'SE01' => 'Melissa internal service error.',
          default => "Melissa service error ({$code}).",
        };
      }
    }

    return null;
  }

  /**
   * Split a Melissa comma-separated code string into an array of trimmed codes.
   *
   * @return array<int, string>
   */
  private function parseCodes(?string $raw): array
  {
    if (!is_string($raw) || $raw === '') {
      return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $raw))));
  }

  /**
   * Build a human-readable URL with the license key masked, for trace/log persistence.
   *
   * @param  array<string, string>  $query
   */
  private function maskedUrl(array $query): string
  {
    $masked = array_merge($query, ['id' => '***']);
    $encoded = http_build_query($masked);
    // `***` would be percent-encoded by http_build_query; restore it for readability.
    $encoded = str_replace('id=%2A%2A%2A', 'id=***', $encoded);

    return self::API_BASE . '?' . $encoded;
  }

  private function licenseKey(LeadQualityProvider $provider): ?string
  {
    $key = $provider->credentials['license_key'] ?? null;
    if (!is_string($key) || trim($key) === '') {
      return null;
    }

    return $key;
  }
}
