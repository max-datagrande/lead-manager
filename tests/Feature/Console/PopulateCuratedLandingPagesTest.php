<?php

use App\Models\LandingPage;
use App\Models\LandingPageVersion;
use App\Models\User;
use App\Models\Vertical;

beforeEach(function () {
  // Vertical/LandingPage setean user_id desde Auth::id() en su hook creating.
  $this->user = User::factory()->create();
  $this->actingAs($this->user);
});

it('crea las landings, versions y verticals faltantes del catalogo', function () {
  $this->artisan('landing-pages:populate-curated')->assertSuccessful();

  // 18 landings (apex + subdominios como landings propias).
  expect(LandingPage::count())->toBe(18);
  // 4 verticals distintas (Auto Insurance, MVA, Distribution, Pet Insurance).
  expect(Vertical::count())->toBe(4);
  expect(Vertical::whereRaw('LOWER(name) = ?', ['pet insurance'])->exists())->toBeTrue();
  expect(Vertical::whereRaw('LOWER(name) = ?', ['distribution'])->exists())->toBeTrue();

  // pawcovered -> Pet Insurance, con sus 2 versions.
  $paw = LandingPage::where('url', 'https://pawcovered.com/')->first();
  expect($paw->vertical->name)->toBe('Pet Insurance');
  expect($paw->versions()->pluck('path')->sort()->values()->all())->toBe(['/', '/rates']);

  // El subdominio quotes. es su PROPIA landing, no se pliega al apex.
  $quotes = LandingPage::where('url', 'https://quotes.top-carinsurance.com/')->first();
  expect($quotes)->not->toBeNull();
  expect($quotes->versions()->count())->toBe(3);

  // total de versions del catalogo.
  expect(LandingPageVersion::count())->toBe(51);
});

it('es idempotente: correrlo dos veces no duplica', function () {
  $this->artisan('landing-pages:populate-curated')->assertSuccessful();
  $this->artisan('landing-pages:populate-curated')->assertSuccessful();

  expect(LandingPage::count())->toBe(18);
  expect(LandingPageVersion::count())->toBe(51);
  expect(Vertical::count())->toBe(4);
});

it('reusa una landing existente por host en vez de duplicar', function () {
  $vertical = Vertical::create(['name' => 'MVA', 'active' => true]);
  // Existente con url sin trailing slash (como en prod) -> mismo host justicepayout.com.
  $existing = LandingPage::create([
    'name' => 'Justice Payout',
    'url' => 'https://justicepayout.com',
    'is_external' => false,
    'vertical_id' => $vertical->id,
    'active' => true,
  ]);

  $this->artisan('landing-pages:populate-curated')->assertSuccessful();

  // No se duplico la landing de justicepayout.
  expect(LandingPage::where('name', 'Justice Payout')->orWhere('url', 'like', '%justicepayout%')->count())->toBe(1);
  // Las versions se colgaron de la landing existente.
  expect($existing->versions()->count())->toBe(6);
});

it('en dry-run no escribe nada', function () {
  $this->artisan('landing-pages:populate-curated', ['--dry-run' => true])->assertSuccessful();

  expect(LandingPage::count())->toBe(0);
  expect(LandingPageVersion::count())->toBe(0);
  expect(Vertical::count())->toBe(0);
});
