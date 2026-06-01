<?php

use App\Models\TrafficLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

/**
 * Crea un TrafficLog con un created_at UTC explicito.
 *
 * created_at no es fillable, por eso se setea via forceFill + saveQuietly
 * para evitar que updateTimestamps lo sobreescriba.
 */
function trafficLogAt(string $utc): TrafficLog
{
  $log = TrafficLog::factory()->create();
  $log->forceFill(['created_at' => $utc])->saveQuietly();

  return $log->fresh();
}

beforeEach(function () {
  $this->admin = User::factory()->create(['role' => 'admin']);
});

test('from_date filter respeta el instante UTC exacto, no la fecha truncada', function () {
  // Bound: from_date = 2026-06-01T04:00:00.000Z (medianoche local UTC-4).
  $before = trafficLogAt('2026-06-01 03:59:59'); // antes del bound -> EXCLUIDO
  $boundary = trafficLogAt('2026-06-01 04:00:00'); // en el bound -> INCLUIDO
  $after = trafficLogAt('2026-06-01 12:00:00'); // despues -> INCLUIDO

  $response = actingAs($this->admin)->get(
    route('visitors.index', [
      'filters' => json_encode([['id' => 'from_date', 'value' => '2026-06-01T04:00:00.000Z']]),
      'per_page' => 100,
    ]),
  );

  $response->assertSuccessful();
  $response->assertInertia(function (AssertableInertia $page) use ($before, $boundary, $after) {
    $rows = collect($page->toArray()['props']['rows']['data'])->pluck('id');

    expect($rows)->not->toContain($before->id);
    expect($rows)->toContain($boundary->id);
    expect($rows)->toContain($after->id);
  });
});

test('from_date + to_date filtran por rango UTC completo', function () {
  $before = trafficLogAt('2026-06-01 03:59:59'); // antes del from -> EXCLUIDO
  $inRange = trafficLogAt('2026-06-01 10:00:00'); // dentro -> INCLUIDO
  $after = trafficLogAt('2026-06-02 04:00:01'); // pasado el to -> EXCLUIDO

  $response = actingAs($this->admin)->get(
    route('visitors.index', [
      'filters' => json_encode([
        ['id' => 'from_date', 'value' => '2026-06-01T04:00:00.000Z'],
        ['id' => 'to_date', 'value' => '2026-06-02T03:59:59.999Z'],
      ]),
      'per_page' => 100,
    ]),
  );

  $response->assertSuccessful();
  $response->assertInertia(function (AssertableInertia $page) use ($before, $inRange, $after) {
    $rows = collect($page->toArray()['props']['rows']['data'])->pluck('id');

    expect($rows)->not->toContain($before->id);
    expect($rows)->toContain($inRange->id);
    expect($rows)->not->toContain($after->id);
  });
});

test('date-only sigue siendo bound inclusivo de dia completo', function () {
  $startOfDay = trafficLogAt('2026-06-01 00:00:00'); // INCLUIDO (from = dia completo)
  $endOfDay = trafficLogAt('2026-06-01 23:59:59'); // INCLUIDO (to = dia completo)
  $nextDay = trafficLogAt('2026-06-02 00:00:00'); // EXCLUIDO

  $response = actingAs($this->admin)->get(
    route('visitors.index', [
      'filters' => json_encode([['id' => 'from_date', 'value' => '2026-06-01'], ['id' => 'to_date', 'value' => '2026-06-01']]),
      'per_page' => 100,
    ]),
  );

  $response->assertSuccessful();
  $response->assertInertia(function (AssertableInertia $page) use ($startOfDay, $endOfDay, $nextDay) {
    $rows = collect($page->toArray()['props']['rows']['data'])->pluck('id');

    expect($rows)->toContain($startOfDay->id);
    expect($rows)->toContain($endOfDay->id);
    expect($rows)->not->toContain($nextDay->id);
  });
});
