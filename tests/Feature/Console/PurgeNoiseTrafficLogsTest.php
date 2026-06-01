<?php

use App\Models\TrafficLog;

function makeLog(string $host, ?string $path): TrafficLog
{
  return TrafficLog::factory()->create(['host' => $host, 'path_visited' => $path]);
}

beforeEach(function () {
  // Host propio de la app: se deriva de APP_URL y nunca se borra.
  config(['app.url' => 'https://admin.datagrande.io']);
});

it('reporta candidatos sin borrar en dry-run', function () {
  makeLog('a-rates.new-site.dev', '/'); // staging
  makeLog('admin.datagrande.io', '/'); // host interno
  makeLog('disaster-recoveryassistance.com', '.env'); // bot
  makeLog('top-carinsurance.com', '/rates'); // real

  $this->artisan('traffic-logs:purge-noise')->assertSuccessful();

  // Nada borrado en dry-run.
  expect(TrafficLog::count())->toBe(4);
});

it('borra staging, hosts internos y paths de bots con --force, dejando el trafico real', function () {
  $staging = makeLog('top-carinsurance.new-site.dev', '/');
  $localhost = makeLog('localhost', '/');
  $botEnv = makeLog('disaster-recoveryassistance.com', '.env');
  $botWp = makeLog('disaster-recoveryassistance.com', 'wp-admin/');
  $botJsp = makeLog('www.top-carinsurance.com', '/wanboguanwangmanbetx/nd.jsp');

  $admin = makeLog('admin.datagrande.io', '/'); // host propio (APP_URL): protegido
  $adminBot = makeLog('admin.datagrande.io', '.env'); // path de bot PERO en host propio: igual protegido
  $real1 = makeLog('top-carinsurance.com', '/rates');
  $real2 = makeLog('pawcovered.com', '/');
  $realHome = makeLog('disaster-recoveryassistance.com', null); // home del host real, NO bot

  $this->artisan('traffic-logs:purge-noise', ['--force' => true])->assertSuccessful();

  // Ruido borrado.
  foreach ([$staging, $localhost, $botEnv, $botWp, $botJsp] as $log) {
    expect(TrafficLog::find($log->id))->toBeNull();
  }

  // Intacto: host propio (incl. con path de bot) + trafico real.
  foreach ([$admin, $adminBot, $real1, $real2, $realHome] as $log) {
    expect(TrafficLog::find($log->id))->not->toBeNull();
  }
});
