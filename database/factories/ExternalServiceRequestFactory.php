<?php

namespace Database\Factories;

use App\Models\ExternalServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExternalServiceRequest>
 */
class ExternalServiceRequestFactory extends Factory
{
  protected $model = ExternalServiceRequest::class;

  public function definition(): array
  {
    return [
      'loggable_type' => null,
      'loggable_id' => null,
      'module' => 'lead_quality',
      'service_name' => 'twilio_verify',
      'service_id' => null,
      'operation' => 'send_challenge',
      'request_method' => 'POST',
      'request_url' => 'https://verify.twilio.com/v2/Services/VAxxxx/Verifications',
      'request_headers' => ['Authorization' => 'Basic ***'],
      'request_body' => ['To' => '+15555551234', 'Channel' => 'sms'],
      'response_status_code' => 201,
      'response_headers' => ['Content-Type' => 'application/json'],
      'response_body' => ['sid' => 'VE' . $this->faker->regexify('[a-f0-9]{32}'), 'status' => 'pending'],
      'status' => 'success',
      'error_message' => null,
      'duration_ms' => $this->faker->numberBetween(80, 800),
      'requested_at' => now(),
      'responded_at' => now()->addMilliseconds(200),
    ];
  }

  public function failed(): static
  {
    return $this->state([
      'status' => 'failed',
      'response_status_code' => 400,
      'response_body' => ['code' => 60200, 'message' => 'Invalid parameter'],
      'error_message' => 'Invalid parameter',
    ]);
  }

  public function timeout(): static
  {
    return $this->state([
      'status' => 'timeout',
      'response_status_code' => null,
      'response_body' => null,
      'error_message' => 'Connection timed out',
    ]);
  }

  public function forVerify(): static
  {
    return $this->state([
      'operation' => 'verify_challenge',
      'request_url' => 'https://verify.twilio.com/v2/Services/VAxxxx/VerificationCheck',
      'request_body' => ['To' => '+15555551234', 'Code' => '123456'],
      'response_body' => ['status' => 'approved'],
    ]);
  }
}
