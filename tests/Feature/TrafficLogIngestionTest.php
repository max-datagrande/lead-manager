<?php

use App\Models\LandingPage;
use App\Models\LandingPageVersion;
use App\Models\TrafficLog;
use App\Models\User;
use App\Services\TrafficLog\TrafficLogCreationException;
use App\Services\TrafficLog\TrafficLogService;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\postJson;

beforeEach(function () {
  // Bypass de auth.host via postman token (mismo patron que DispatchApiTest).
  config(['app.postman_auth_enabled' => true, 'services.postman_auth_token' => 'test-token']);

  // LandingPage/Vertical setean user_id desde Auth::id() en su hook creating.
  $this->actingAs(User::factory()->create());

  // Precachear la geo de la IP de test para no pegarle a la API externa.
  Cache::put('geolocation:ip:216.131.83.235', ['country' => 'US', 'region' => 'NY', 'city' => 'New York', 'postal' => '10001'], now()->addHour());
});

function visitorHeaders(string $origin = 'http://moonautoinsurance.test'): array
{
  return [
    'X-Postman-Auth-Token' => config('services.postman_auth_token', 'test-token'),
    'origin' => $origin,
  ];
}

function visitorPayload(array $overrides = []): array
{
  return array_merge(
    [
      'user_agent' => 'Mozilla/5.0 (Test Browser) Chrome/120.0',
      'referer' => null,
      'query_params' => [],
      'current_page' => '/offers/',
    ],
    $overrides,
  );
}

it('vincula el traffic log con landing y version cuando viene landing_id valido', function () {
  $landing = LandingPage::factory()->create(['url' => 'http://moonautoinsurance.test/']);
  $version = LandingPageVersion::create([
    'landing_page_id' => $landing->id,
    'name' => 'Offers',
    'path' => '/offers/',
    'status' => true,
  ]);

  postJson('/v1/visitor/register', visitorPayload(['landing_id' => $landing->id]), visitorHeaders())->assertStatus(201);

  $log = TrafficLog::first();
  expect($log->landing_id)->toBe($landing->id);
  expect($log->landing_page_version_id)->toBe($version->id);
});

it('auto-resuelve landing y version por host+path cuando no viene landing_id', function () {
  $landing = LandingPage::factory()->create(['url' => 'http://moonautoinsurance.test/']);
  $version = LandingPageVersion::create([
    'landing_page_id' => $landing->id,
    'name' => 'Offers',
    'path' => '/offers/',
    'status' => true,
  ]);

  postJson('/v1/visitor/register', visitorPayload(), visitorHeaders())->assertStatus(201);

  $log = TrafficLog::first();
  expect($log->landing_id)->toBe($landing->id);
  expect($log->landing_page_version_id)->toBe($version->id);
});

it('guarda el traffic log sin vinculo cuando el host no matchea ninguna landing', function () {
  LandingPage::factory()->create(['url' => 'http://moonautoinsurance.test/']);

  postJson('/v1/visitor/register', visitorPayload(), visitorHeaders('http://unknown-domain.test'))->assertStatus(201);

  $log = TrafficLog::first();
  expect($log->landing_id)->toBeNull();
  expect($log->landing_page_version_id)->toBeNull();
});

it('rechaza con 422 cuando el landing_id no existe', function () {
  postJson('/v1/visitor/register', visitorPayload(['landing_id' => 999999]), visitorHeaders())
    ->assertStatus(422)
    ->assertJsonValidationErrors(['landing_id']);

  expect(TrafficLog::count())->toBe(0);
});

it('responde 500 generico y corre el debug del alert (unwrap de causa raiz) cuando la creacion falla', function () {
  // Sin webhook configurado, sendDirect short-circuita antes de cualquier curl real.
  config(['slack-alerts.webhook_urls' => []]);

  // Simula la causa raiz real observada en prod: truncacion de varchar(255).
  $rootCause = new \RuntimeException('SQLSTATE[22001]: String data, right truncated: value too long for type character varying(255)');

  // El service envuelve toda falla en TrafficLogCreationException con la causa como previous.
  $this->mock(TrafficLogService::class, function ($mock) use ($rootCause) {
    $mock->shouldReceive('createTrafficLog')->once()->andThrow(new TrafficLogCreationException('Failed to create traffic log', 0, $rootCause));
  });

  // Si el path de debug (unwrapException/notifySlack/errorContext) explotara, este request
  // no devolveria un 500 limpio. El mensaje al cliente queda generico (no filtra el SQL).
  postJson('/v1/visitor/register', visitorPayload(), visitorHeaders())->assertStatus(500)->assertJsonPath('message', 'Failed to create traffic log');

  expect(TrafficLog::count())->toBe(0);
});
