<?php

namespace App\Services\LeadQuality\Providers;

use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use App\Services\ExternalServiceRequest\ExternalRequestRecorder;
use App\Services\LeadQuality\Contracts\LeadQualityProviderInterface;
use App\Services\LeadQuality\DTO\ChallengeResult;
use App\Services\LeadQuality\DTO\TestConnectionResult;
use App\Services\LeadQuality\DTO\VerifyResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class TwilioVerifyProvider implements LeadQualityProviderInterface
{
  private const API_BASE = 'https://verify.twilio.com/v2';

  public function __construct(private readonly ExternalRequestRecorder $recorder) {}

  public function sendChallenge(
    LeadQualityProvider $provider,
    LeadQualityValidationRule $rule,
    LeadQualityValidationLog $log,
    array $context,
  ): ChallengeResult {
    $creds = $provider->credentials ?? [];
    $to = $context['to'] ?? null;
    $channel = $context['channel'] ?? ($rule->settings['channel'] ?? 'sms');

    if (!$to || !$this->hasCredentials($creds)) {
      return ChallengeResult::failure($this->credentialError($creds, $to));
    }

    $url = self::API_BASE . '/Services/' . $creds['verify_service_sid'] . '/Verifications';
    $body = ['To' => (string) $to, 'Channel' => (string) $channel];

    if (!empty($context['locale'])) {
      $body['Locale'] = (string) $context['locale'];
    }

    try {
      $response = $this->recorder->record(
        fn(): Response => Http::withBasicAuth($creds['account_sid'], $creds['auth_token'])->asForm()->acceptJson()->timeout(10)->post($url, $body),
        [
          'module' => 'lead_quality',
          'service_name' => 'twilio_verify',
          'service_id' => $provider->id,
          'operation' => 'send_challenge',
          'loggable' => $log,
          'request_method' => 'POST',
          'request_url' => $url,
          'request_body' => $body,
          'request_headers' => ['Authorization' => 'Basic ***'],
        ],
      );
    } catch (ConnectionException $e) {
      return ChallengeResult::failure('Connection to Twilio timed out.');
    } catch (Throwable $e) {
      return ChallengeResult::failure($e->getMessage());
    }

    $json = $response->json() ?? [];

    if (!$response->successful()) {
      return ChallengeResult::failure($json['message'] ?? "Twilio HTTP {$response->status()}", $json);
    }

    $sid = $json['sid'] ?? null;
    if (!$sid) {
      return ChallengeResult::failure('Twilio response missing verification sid.', $json);
    }

    return ChallengeResult::success(reference: (string) $sid, maskedDestination: $this->maskDestination((string) $to), raw: $json);
  }

  public function verifyChallenge(
    LeadQualityProvider $provider,
    LeadQualityValidationRule $rule,
    LeadQualityValidationLog $log,
    string $code,
    array $context,
  ): VerifyResult {
    $creds = $provider->credentials ?? [];
    $to = $context['to'] ?? null;

    if (!$to || !$this->hasCredentials($creds)) {
      return VerifyResult::failure($this->credentialError($creds, $to));
    }

    $url = self::API_BASE . '/Services/' . $creds['verify_service_sid'] . '/VerificationCheck';
    $body = ['To' => (string) $to, 'Code' => $code];

    try {
      $response = $this->recorder->record(
        fn(): Response => Http::withBasicAuth($creds['account_sid'], $creds['auth_token'])->asForm()->acceptJson()->timeout(10)->post($url, $body),
        [
          'module' => 'lead_quality',
          'service_name' => 'twilio_verify',
          'service_id' => $provider->id,
          'operation' => 'verify_challenge',
          'loggable' => $log,
          'request_method' => 'POST',
          'request_url' => $url,
          'request_body' => ['To' => $body['To'], 'Code' => '***'],
          'request_headers' => ['Authorization' => 'Basic ***'],
        ],
      );
    } catch (ConnectionException $e) {
      return VerifyResult::failure('Connection to Twilio timed out.');
    } catch (Throwable $e) {
      return VerifyResult::failure($e->getMessage());
    }

    $json = $response->json() ?? [];

    if (!$response->successful()) {
      return VerifyResult::failure($json['message'] ?? "Twilio HTTP {$response->status()}", $json);
    }

    $twilioStatus = $json['status'] ?? null;
    if ($twilioStatus === 'approved') {
      return VerifyResult::success($json);
    }

    return VerifyResult::failure("Code not approved (status: {$twilioStatus})", $json);
  }

  public function testConnection(LeadQualityProvider $provider): TestConnectionResult
  {
    $creds = $provider->credentials ?? [];

    if (!$this->hasCredentials($creds)) {
      return TestConnectionResult::failure('Provider is missing required Twilio credentials (account_sid, auth_token, verify_service_sid).');
    }

    $url = self::API_BASE . '/Services/' . $creds['verify_service_sid'];

    try {
      $response = $this->recorder->record(
        fn(): Response => Http::withBasicAuth($creds['account_sid'], $creds['auth_token'])->acceptJson()->timeout(10)->get($url),
        [
          'module' => 'lead_quality',
          'service_name' => 'twilio_verify',
          'service_id' => $provider->id,
          'operation' => 'test_connection',
          'request_method' => 'GET',
          'request_url' => $url,
          'request_headers' => ['Authorization' => 'Basic ***'],
        ],
      );
    } catch (ConnectionException $e) {
      return TestConnectionResult::failure('Connection to Twilio timed out.');
    } catch (Throwable $e) {
      return TestConnectionResult::failure($e->getMessage());
    }

    $json = $response->json() ?? [];

    if ($response->successful()) {
      $friendlyName = $json['friendly_name'] ?? $creds['verify_service_sid'];
      return TestConnectionResult::success("Connected to Verify Service: {$friendlyName}", $json);
    }

    return TestConnectionResult::failure($json['message'] ?? "Twilio responded HTTP {$response->status()}", $json);
  }

  /**
   * @param  array<string, mixed>  $creds
   */
  private function hasCredentials(array $creds): bool
  {
    return !empty($creds['account_sid']) && !empty($creds['auth_token']) && !empty($creds['verify_service_sid']);
  }

  /**
   * @param  array<string, mixed>  $creds
   */
  private function credentialError(array $creds, mixed $to): string
  {
    if (!$to) {
      return 'Missing destination for challenge (context.to).';
    }

    return 'Provider credentials are incomplete.';
  }

  private function maskDestination(string $to): string
  {
    $digits = preg_replace('/\D/', '', $to) ?? '';
    $last = substr($digits, -4);
    $prefix = str_starts_with($to, '+') ? '+' : '';

    return "{$prefix}" . str_repeat('*', max(0, strlen($digits) - 4)) . $last;
  }
}
