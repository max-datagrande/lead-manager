<?php

use App\Models\TrafficLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\postJson;

beforeEach(function () {
  // Bypass de auth.host via postman token (mismo patron que TrafficLogIngestionTest).
  config(['app.postman_auth_enabled' => true, 'services.postman_auth_token' => 'test-token']);
  $this->actingAs(User::factory()->create());

  // Precachear la geo de la IP de test para no pegarle a la API externa.
  Cache::put('geolocation:ip:216.131.83.235', ['country' => 'US', 'region' => 'NY', 'city' => 'New York', 'postal' => '10001'], now()->addHour());
});

function updateVisitHeaders(string $origin = 'http://moonautoinsurance.test'): array
{
  return [
    'X-Postman-Auth-Token' => config('services.postman_auth_token', 'test-token'),
    'origin' => $origin,
  ];
}

/**
 * Registra una visita y devuelve su fingerprint, para luego patchearla.
 */
function registerVisitFingerprint(): string
{
  $payload = [
    'user_agent' => 'Mozilla/5.0 (Test Browser) Chrome/120.0',
    'referer' => null,
    'query_params' => [],
    'current_page' => '/offers/',
  ];

  return postJson('/v1/visitor/register', $payload, updateVisitHeaders())->assertStatus(201)->json('fingerprint');
}

it('patchea el s10 de una visita ya registrada matcheando por fingerprint', function () {
  $fingerprint = registerVisitFingerprint();
  expect(TrafficLog::where('fingerprint', $fingerprint)->value('s10'))->toBeNull();

  $s10 = '2c802014-5bde-4240-8b75-251a20fd0c14';

  postJson('/v1/visitor/update', ['fingerprint' => $fingerprint, 's10' => $s10], updateVisitHeaders())
    ->assertStatus(200)
    ->assertJsonPath('success', true)
    ->assertJsonPath('data.fingerprint', $fingerprint)
    ->assertJsonPath('data.s10', $s10);

  expect(TrafficLog::where('fingerprint', $fingerprint)->value('s10'))->toBe($s10);
});

it('hace eco generico de cada columna actualizable recibida (s10 + s1)', function () {
  $fingerprint = registerVisitFingerprint();

  postJson('/v1/visitor/update', ['fingerprint' => $fingerprint, 's10' => 'click-xyz', 's1' => 'sub-one'], updateVisitHeaders())
    ->assertStatus(200)
    ->assertJsonPath('data.s10', 'click-xyz')
    ->assertJsonPath('data.s1', 'sub-one');

  $log = TrafficLog::where('fingerprint', $fingerprint)->first();
  expect($log->s10)->toBe('click-xyz');
  expect($log->s1)->toBe('sub-one');
});

it('no hace eco de columnas no enviadas', function () {
  $fingerprint = registerVisitFingerprint();

  postJson('/v1/visitor/update', ['fingerprint' => $fingerprint, 's10' => 'only-s10'], updateVisitHeaders())
    ->assertStatus(200)
    ->assertJsonPath('data.s10', 'only-s10')
    ->assertJsonMissingPath('data.s1');
});

it('es idempotente: aplicar el mismo s10 dos veces resuelve success sin romper', function () {
  $fingerprint = registerVisitFingerprint();
  $s10 = '2c802014-5bde-4240-8b75-251a20fd0c14';

  postJson('/v1/visitor/update', ['fingerprint' => $fingerprint, 's10' => $s10], updateVisitHeaders())->assertStatus(200);
  postJson('/v1/visitor/update', ['fingerprint' => $fingerprint, 's10' => $s10], updateVisitHeaders())
    ->assertStatus(200)
    ->assertJsonPath('data.s10', $s10);

  expect(TrafficLog::where('fingerprint', $fingerprint)->count())->toBe(1);
});

it('devuelve 404 con code NO_ACTIVE_VISIT cuando el fingerprint no existe', function () {
  postJson('/v1/visitor/update', ['fingerprint' => 'nonexistent-fp', 's10' => 'whatever'], updateVisitHeaders())
    ->assertStatus(404)
    ->assertJsonPath('success', false)
    ->assertJsonPath('errors.code', 'NO_ACTIVE_VISIT');
});

it('permite actualizar columnas de tracking sin s10 (todas opcionales salvo fingerprint)', function () {
  $fingerprint = registerVisitFingerprint();

  postJson('/v1/visitor/update', ['fingerprint' => $fingerprint, 's1' => 'sub-one', 'utm_source' => 'youtube'], updateVisitHeaders())
    ->assertStatus(200)
    ->assertJsonPath('data.s1', 'sub-one')
    ->assertJsonPath('data.utm_source', 'youtube');

  $log = TrafficLog::where('fingerprint', $fingerprint)->first();
  expect($log->s1)->toBe('sub-one');
  expect($log->utm_source)->toBe('youtube');
});

it('rechaza con 422 cuando falta fingerprint', function () {
  postJson('/v1/visitor/update', ['s10' => 'abc'], updateVisitHeaders())
    ->assertStatus(422)
    ->assertJsonValidationErrors(['fingerprint']);
});
