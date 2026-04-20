<?php

use App\Enums\LeadQuality\LeadQualityProviderType;
use App\Enums\LeadQuality\ProviderStatus;
use App\Models\LeadQualityProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('casts type and status to enums', function () {
  $provider = LeadQualityProvider::factory()->create();

  expect($provider->type)->toBeInstanceOf(LeadQualityProviderType::class);
  expect($provider->status)->toBeInstanceOf(ProviderStatus::class);
});

it('encrypts credentials at rest', function () {
  $provider = LeadQualityProvider::factory()->create([
    'credentials' => ['auth_token' => 'super-secret-123'],
  ]);

  $raw = \Illuminate\Support\Facades\DB::table('lead_quality_providers')->where('id', $provider->id)->value('credentials');

  expect($raw)->toBeString();
  expect($raw)->not->toContain('super-secret-123');
  expect($provider->fresh()->credentials)->toBe(['auth_token' => 'super-secret-123']);
});

it('hides credentials when serialized', function () {
  $provider = LeadQualityProvider::factory()->create();

  expect($provider->toArray())->not->toHaveKey('credentials');
});

it('considers provider usable when active and enabled', function () {
  $usable = LeadQualityProvider::factory()->active()->create();
  $disabled = LeadQualityProvider::factory()->disabled()->create();

  expect($usable->isUsable())->toBeTrue();
  expect($disabled->isUsable())->toBeFalse();
});

it('masks sensitive credential keys', function () {
  $provider = LeadQualityProvider::factory()->create([
    'credentials' => [
      'account_sid' => 'AC1234567890abcdef',
      'auth_token' => 'supersecretvalue1234',
    ],
  ]);

  $masked = $provider->maskedCredentials();

  expect($masked['account_sid'])->toBe('AC1234567890abcdef');
  expect($masked['auth_token'])->toEndWith('1234');
  expect($masked['auth_token'])->not->toBe('supersecretvalue1234');
});

it('exposes toArray on enum with implementation flag', function () {
  $array = LeadQualityProviderType::toArray();

  expect($array)->toBeArray();
  $twilio = collect($array)->firstWhere('value', 'twilio_verify');
  expect($twilio['is_implemented'])->toBeTrue();

  $ipqs = collect($array)->firstWhere('value', 'ipqs');
  expect($ipqs['is_implemented'])->toBeFalse();
});
