<?php

namespace App\Services\LeadQuality;

use App\Enums\LeadQuality\LeadQualityProviderType;
use App\Models\LeadQualityProvider;
use App\Services\ExternalServiceRequest\ExternalRequestRecorder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Admin-only smoke tester for a provider's OTP challenge + verify cycle.
 *
 * Runs the send/verify HTTP pair against the provider's live credentials
 * without touching any business state — no LeadDispatch, no ValidationLog,
 * no timeline event. Technical request/response rows are still persisted in
 * `external_service_requests` (operation=test_send|test_verify, loggable=
 * the provider itself) so the admin keeps the audit trail.
 */
class OtpProviderTester
{
  private const TWILIO_API_BASE = 'https://verify.twilio.com/v2';

  public function __construct(private readonly ExternalRequestRecorder $recorder) {}

  /**
   * @return array{ok: bool, verification_sid?: string, masked_destination?: string, status?: ?string, error?: string, raw?: array}
   */
  public function testSend(LeadQualityProvider $provider, string $to, string $channel = 'sms', ?string $locale = null): array
  {
    if ($provider->type !== LeadQualityProviderType::TWILIO_VERIFY) {
      return ['ok' => false, 'error' => 'Only Twilio Verify is supported by the OTP tester right now.'];
    }

    $creds = $provider->credentials ?? [];
    if (!$this->hasTwilioCredentials($creds)) {
      return ['ok' => false, 'error' => 'Provider is missing required Twilio credentials.'];
    }

    $url = self::TWILIO_API_BASE . '/Services/' . $creds['verify_service_sid'] . '/Verifications';
    $body = ['To' => $to, 'Channel' => $channel];
    if ($locale) {
      $body['Locale'] = $locale;
    }

    try {
      $response = $this->recorder->record(
        fn(): Response => Http::withBasicAuth($creds['account_sid'], $creds['auth_token'])->asForm()->acceptJson()->timeout(10)->post($url, $body),
        [
          'module' => 'lead_quality',
          'service_name' => 'twilio_verify',
          'service_id' => $provider->id,
          'operation' => 'test_send',
          'loggable' => $provider,
          'request_method' => 'POST',
          'request_url' => $url,
          'request_body' => $body,
          'request_headers' => ['Authorization' => 'Basic ***'],
        ],
      );
    } catch (ConnectionException) {
      return ['ok' => false, 'error' => 'Connection to Twilio timed out.'];
    } catch (Throwable $e) {
      return ['ok' => false, 'error' => $e->getMessage()];
    }

    $json = $response->json() ?? [];

    if (!$response->successful()) {
      return ['ok' => false, 'error' => $json['message'] ?? "Twilio HTTP {$response->status()}", 'raw' => $json];
    }

    return [
      'ok' => true,
      'verification_sid' => (string) ($json['sid'] ?? ''),
      'masked_destination' => $this->maskDestination($to),
      'status' => $json['status'] ?? null,
      'raw' => $json,
    ];
  }

  /**
   * @return array{ok: bool, status?: ?string, error?: string, raw?: array}
   */
  public function testVerify(LeadQualityProvider $provider, string $to, string $code): array
  {
    if ($provider->type !== LeadQualityProviderType::TWILIO_VERIFY) {
      return ['ok' => false, 'error' => 'Only Twilio Verify is supported by the OTP tester right now.'];
    }

    $creds = $provider->credentials ?? [];
    if (!$this->hasTwilioCredentials($creds)) {
      return ['ok' => false, 'error' => 'Provider is missing required Twilio credentials.'];
    }

    $url = self::TWILIO_API_BASE . '/Services/' . $creds['verify_service_sid'] . '/VerificationCheck';
    $body = ['To' => $to, 'Code' => $code];

    try {
      $response = $this->recorder->record(
        fn(): Response => Http::withBasicAuth($creds['account_sid'], $creds['auth_token'])->asForm()->acceptJson()->timeout(10)->post($url, $body),
        [
          'module' => 'lead_quality',
          'service_name' => 'twilio_verify',
          'service_id' => $provider->id,
          'operation' => 'test_verify',
          'loggable' => $provider,
          'request_method' => 'POST',
          'request_url' => $url,
          // Mask the code so auditors can never reconstruct the OTP from logs.
          'request_body' => ['To' => $to, 'Code' => '***'],
          'request_headers' => ['Authorization' => 'Basic ***'],
        ],
      );
    } catch (ConnectionException) {
      return ['ok' => false, 'error' => 'Connection to Twilio timed out.'];
    } catch (Throwable $e) {
      return ['ok' => false, 'error' => $e->getMessage()];
    }

    $json = $response->json() ?? [];

    if (!$response->successful()) {
      return ['ok' => false, 'error' => $json['message'] ?? "Twilio HTTP {$response->status()}", 'raw' => $json];
    }

    $status = $json['status'] ?? null;
    return [
      'ok' => $status === 'approved',
      'status' => $status,
      'error' => $status === 'approved' ? null : "Code not approved (status: {$status}).",
      'raw' => $json,
    ];
  }

  /**
   * @param  array<string, mixed>  $creds
   */
  private function hasTwilioCredentials(array $creds): bool
  {
    return !empty($creds['account_sid']) && !empty($creds['auth_token']) && !empty($creds['verify_service_sid']);
  }

  private function maskDestination(string $to): string
  {
    $len = strlen($to);
    if ($len <= 4) {
      return $to;
    }
    return substr($to, 0, 2) . str_repeat('*', max(3, $len - 6)) . substr($to, -4);
  }
}
