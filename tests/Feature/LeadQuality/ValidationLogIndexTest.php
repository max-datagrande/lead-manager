<?php

use App\Enums\LeadQuality\ValidationLogStatus;
use App\Models\ExternalServiceRequest;
use App\Models\Integration;
use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeEach(function () {
  $this->admin = User::factory()->create(['role' => 'admin']);
});

test('admin sees the validation logs index with server-side meta', function () {
  LeadQualityValidationLog::factory()->count(3)->create();

  $response = actingAs($this->admin)->get(route('lead-quality.validation-logs.index'));

  $response->assertSuccessful();
  $response->assertInertia(
    fn(AssertableInertia $page) => $page
      ->component('lead-quality/validation-logs/index')
      ->has('rows.data', 3)
      ->has('meta')
      ->has('data.status_options')
      ->has('data.rules')
      ->has('data.providers')
      ->has('data.buyers'),
  );
});

test('index applies status filter via filters query parameter', function () {
  LeadQualityValidationLog::factory()->verified()->count(2)->create();
  LeadQualityValidationLog::factory()->failed()->count(1)->create();

  $response = actingAs($this->admin)->get(
    route('lead-quality.validation-logs.index', [
      'filters' => json_encode([['id' => 'status', 'value' => [ValidationLogStatus::VERIFIED->value]]]),
    ]),
  );

  $response->assertSuccessful();
  $response->assertInertia(fn(AssertableInertia $page) => $page->has('rows.data', 2));
});

test('index filters by validation_rule_id and integration_id', function () {
  $rule = LeadQualityValidationRule::factory()->create();
  $buyer = Integration::factory()->create();
  $otherRule = LeadQualityValidationRule::factory()->create();

  LeadQualityValidationLog::factory()->create([
    'validation_rule_id' => $rule->id,
    'integration_id' => $buyer->id,
  ]);
  LeadQualityValidationLog::factory()
    ->count(2)
    ->create([
      'validation_rule_id' => $otherRule->id,
    ]);

  $response = actingAs($this->admin)->get(
    route('lead-quality.validation-logs.index', [
      'filters' => json_encode([
        ['id' => 'validation_rule_id', 'value' => (string) $rule->id],
        ['id' => 'integration_id', 'value' => (string) $buyer->id],
      ]),
    ]),
  );

  $response->assertSuccessful();
  $response->assertInertia(fn(AssertableInertia $page) => $page->has('rows.data', 1));
});

test('index searches fingerprint via search query', function () {
  LeadQualityValidationLog::factory()->create(['fingerprint' => 'abc-unique-fingerprint-123']);
  LeadQualityValidationLog::factory()->count(2)->create();

  $response = actingAs($this->admin)->get(route('lead-quality.validation-logs.index', ['search' => 'unique-fingerprint']));

  $response->assertSuccessful();
  $response->assertInertia(fn(AssertableInertia $page) => $page->has('rows.data', 1));
});

test('show endpoint returns a single log with its relations serialized', function () {
  $rule = LeadQualityValidationRule::factory()->create();
  $provider = LeadQualityProvider::factory()->create();
  $buyer = Integration::factory()->create();

  $log = LeadQualityValidationLog::factory()
    ->verified()
    ->create([
      'validation_rule_id' => $rule->id,
      'provider_id' => $provider->id,
      'integration_id' => $buyer->id,
    ]);

  $response = actingAs($this->admin)->getJson(route('lead-quality.validation-logs.show', $log));

  $response->assertOk();
  $response->assertJsonPath('log.id', $log->id);
  $response->assertJsonPath('log.status', 'verified');
  $response->assertJsonPath('log.rule_detail.id', $rule->id);
  $response->assertJsonPath('log.provider_detail.id', $provider->id);
  $response->assertJsonPath('log.buyer.id', $buyer->id);
});

test('technical endpoint returns external_service_requests attached to the log', function () {
  $log = LeadQualityValidationLog::factory()->sent()->create();

  ExternalServiceRequest::factory()->create([
    'loggable_type' => LeadQualityValidationLog::class,
    'loggable_id' => $log->id,
    'operation' => 'send_challenge',
  ]);
  ExternalServiceRequest::factory()
    ->forVerify()
    ->create([
      'loggable_type' => LeadQualityValidationLog::class,
      'loggable_id' => $log->id,
      'operation' => 'verify_challenge',
    ]);

  $unrelated = LeadQualityValidationLog::factory()->create();
  ExternalServiceRequest::factory()->create([
    'loggable_type' => LeadQualityValidationLog::class,
    'loggable_id' => $unrelated->id,
  ]);

  $response = actingAs($this->admin)->getJson(route('lead-quality.validation-logs.technical', $log));

  $response->assertOk();
  $response->assertJsonCount(2, 'requests');
  $operations = collect($response->json('requests'))->pluck('operation')->sort()->values()->all();
  expect($operations)->toBe(['send_challenge', 'verify_challenge']);
});

test('index eager-loads rule/provider/buyer to avoid N+1', function () {
  LeadQualityValidationLog::factory()->count(5)->create();

  DB::enableQueryLog();
  actingAs($this->admin)->get(route('lead-quality.validation-logs.index'))->assertSuccessful();
  $queries = DB::getQueryLog();
  DB::disableQueryLog();

  // Inertia rendering itself fires auxiliary queries; rule/provider/integration must NOT be queried once per row.
  $perRowRelationQueries = collect($queries)->filter(
    fn(array $q) => preg_match(
      '/select \* from "(lead_quality_validation_rules|lead_quality_providers|integrations)" where .* in \(\?\)/i',
      $q['query'],
    ),
  );

  expect($perRowRelationQueries)->toHaveCount(0, 'Per-row lookups detected — eager loading is missing.');
});

test('non-admin cannot access validation logs', function () {
  $user = User::factory()->create(['role' => 'user']);

  actingAs($user)->get(route('lead-quality.validation-logs.index'))->assertForbidden();
  $log = LeadQualityValidationLog::factory()->create();
  actingAs($user)->getJson(route('lead-quality.validation-logs.show', $log))->assertForbidden();
  actingAs($user)->getJson(route('lead-quality.validation-logs.technical', $log))->assertForbidden();
});
