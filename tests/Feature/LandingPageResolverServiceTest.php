<?php

use App\Models\LandingPage;
use App\Models\LandingPageVersion;
use App\Models\User;
use App\Services\LandingPageResolverService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
  // Los modelos LandingPage/Vertical setean user_id desde Auth::id() en su hook creating.
  $this->actingAs(User::factory()->create());
  $this->resolver = app(LandingPageResolverService::class);
  Cache::flush();
});

function makeLanding(string $url, bool $active = true): LandingPage
{
  return LandingPage::factory()->create(['url' => $url, 'active' => $active]);
}

function makeVersion(LandingPage $landing, string $path, bool $status = true): LandingPageVersion
{
  return LandingPageVersion::create([
    'landing_page_id' => $landing->id,
    'name' => 'v-' . $path,
    'path' => $path,
    'status' => $status,
  ]);
}

it('respeta el landing_id explicito y resuelve la version por path', function () {
  $landing = makeLanding('http://moonautoinsurance.test/');
  $version = makeVersion($landing, '/offers/');

  $result = $this->resolver->resolve($landing->id, 'irrelevant-host.test', '/offers/');

  expect($result['landing_id'])->toBe($landing->id);
  expect($result['landing_page_version_id'])->toBe($version->id);
});

it('deduce la landing por host cuando no viene landing_id', function () {
  $landing = makeLanding('http://moonautoinsurance.test/');

  $result = $this->resolver->resolve(null, 'moonautoinsurance.test', '/');

  expect($result['landing_id'])->toBe($landing->id);
});

it('prefiere la landing activa cuando hay varias con el mismo host', function () {
  // landing_pages.url es UNIQUE: mismo host, distinto path para poder tener dos filas.
  makeLanding('http://moonautoinsurance.test/old', active: false);
  $active = makeLanding('http://moonautoinsurance.test/', active: true);

  $result = $this->resolver->resolve(null, 'moonautoinsurance.test', '/');

  expect($result['landing_id'])->toBe($active->id);
});

it('trata www.host igual que el host pelado', function () {
  $landing = makeLanding('http://moonautoinsurance.test/');

  $result = $this->resolver->resolve(null, 'www.moonautoinsurance.test', '/');

  expect($result['landing_id'])->toBe($landing->id);
});

it('resuelve aunque el host venga con punto final o en mayusculas', function () {
  $landing = makeLanding('http://moonautoinsurance.test/');

  expect($this->resolver->resolve(null, 'moonautoinsurance.test.', '/')['landing_id'])->toBe($landing->id);
  expect($this->resolver->resolve(null, 'MOONAUTOINSURANCE.TEST', '/')['landing_id'])->toBe($landing->id);
});

it('no resuelve subdominios arbitrarios (queda para el ticket de alias)', function () {
  makeLanding('http://moonautoinsurance.test/');

  $result = $this->resolver->resolve(null, 'quotes.moonautoinsurance.test', '/');

  expect($result['landing_id'])->toBeNull();
  expect($result['landing_page_version_id'])->toBeNull();
});

it('deja version_id en null cuando no hay version para ese path', function () {
  $landing = makeLanding('http://moonautoinsurance.test/');
  makeVersion($landing, '/offers/');

  $result = $this->resolver->resolve($landing->id, 'moonautoinsurance.test', '/no-existe');

  expect($result['landing_id'])->toBe($landing->id);
  expect($result['landing_page_version_id'])->toBeNull();
});

it('devuelve ambos null cuando el host no matchea ninguna landing', function () {
  makeLanding('http://moonautoinsurance.test/');

  $result = $this->resolver->resolve(null, 'unknown-domain.test', '/');

  expect($result['landing_id'])->toBeNull();
  expect($result['landing_page_version_id'])->toBeNull();
});

it('cachea el lookup de host: el segundo resolve no consulta landing_pages', function () {
  $landing = makeLanding('http://moonautoinsurance.test/');

  // Primer call: calienta el cache.
  $this->resolver->resolve(null, 'moonautoinsurance.test', '/');

  DB::enableQueryLog();
  $result = $this->resolver->resolve(null, 'moonautoinsurance.test', '/');
  $queries = DB::getQueryLog();
  DB::disableQueryLog();

  expect($result['landing_id'])->toBe($landing->id);
  $touchedLandingPages = collect($queries)->contains(fn($q) => str_contains($q['query'], 'landing_pages'));
  expect($touchedLandingPages)->toBeFalse();
});
