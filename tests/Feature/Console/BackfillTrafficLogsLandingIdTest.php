<?php

use App\Models\LandingPage;
use App\Models\LandingPageVersion;
use App\Models\TrafficLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
  $this->actingAs(User::factory()->create());
  Cache::flush();

  $this->landing = LandingPage::factory()->create(['url' => 'http://moonautoinsurance.test/']);
  $this->version = LandingPageVersion::create([
    'landing_page_id' => $this->landing->id,
    'name' => 'Offers',
    'path' => '/offers/',
    'status' => true,
  ]);
});

it('vincula logs historicos sin landing_id con host+path matcheable', function () {
  $log = TrafficLog::factory()->create([
    'host' => 'moonautoinsurance.test',
    'path_visited' => '/offers/',
    'landing_id' => null,
    'landing_page_version_id' => null,
  ]);

  $this->artisan('traffic-logs:backfill-landing-id')->assertSuccessful();

  $log->refresh();
  expect($log->landing_id)->toBe($this->landing->id);
  expect($log->landing_page_version_id)->toBe($this->version->id);
});

it('no escribe nada en dry-run', function () {
  $log = TrafficLog::factory()->create([
    'host' => 'moonautoinsurance.test',
    'path_visited' => '/offers/',
    'landing_id' => null,
    'landing_page_version_id' => null,
  ]);

  $this->artisan('traffic-logs:backfill-landing-id', ['--dry-run' => true])->assertSuccessful();

  $log->refresh();
  expect($log->landing_id)->toBeNull();
  expect($log->landing_page_version_id)->toBeNull();
});

it('no toca logs que ya estaban completamente vinculados', function () {
  $log = TrafficLog::factory()->create([
    'host' => 'moonautoinsurance.test',
    'path_visited' => '/offers/',
    'landing_id' => $this->landing->id,
    'landing_page_version_id' => $this->version->id,
  ]);

  $this->artisan('traffic-logs:backfill-landing-id')->assertSuccessful();

  $log->refresh();
  expect($log->landing_id)->toBe($this->landing->id);
  expect($log->landing_page_version_id)->toBe($this->version->id);
});

it('completa version_id en logs con landing_id ya seteado pero version null (pase B)', function () {
  $log = TrafficLog::factory()->create([
    'host' => 'moonautoinsurance.test',
    'path_visited' => '/offers/',
    'landing_id' => $this->landing->id,
    'landing_page_version_id' => null,
  ]);

  $this->artisan('traffic-logs:backfill-landing-id')->assertSuccessful();

  $log->refresh();
  expect($log->landing_id)->toBe($this->landing->id);
  expect($log->landing_page_version_id)->toBe($this->version->id);
});

it('no ejecuta el pase B en dry-run', function () {
  $log = TrafficLog::factory()->create([
    'host' => 'moonautoinsurance.test',
    'path_visited' => '/offers/',
    'landing_id' => $this->landing->id,
    'landing_page_version_id' => null,
  ]);

  $this->artisan('traffic-logs:backfill-landing-id', ['--dry-run' => true])->assertSuccessful();

  $log->refresh();
  expect($log->landing_page_version_id)->toBeNull();
});
