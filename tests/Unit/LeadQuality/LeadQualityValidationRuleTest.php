<?php

use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationType;
use App\Models\Integration;
use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('auto-generates slug from name on create', function () {
  $rule = LeadQualityValidationRule::factory()->create([
    'name' => 'Premium Buyers OTP',
    'slug' => null,
  ]);

  expect($rule->slug)->toBe('premium-buyers-otp');
});

it('increments slug when name collides', function () {
  LeadQualityValidationRule::factory()->create(['name' => 'Collision Rule', 'slug' => null]);
  $second = LeadQualityValidationRule::factory()->create(['name' => 'Collision Rule', 'slug' => null]);

  expect($second->slug)->toBe('collision-rule-2');
});

it('casts validation_type and status to enums', function () {
  $rule = LeadQualityValidationRule::factory()->create();

  expect($rule->validation_type)->toBeInstanceOf(ValidationType::class);
  expect($rule->status)->toBeInstanceOf(RuleStatus::class);
});

it('belongs to a provider', function () {
  $provider = LeadQualityProvider::factory()->create();
  $rule = LeadQualityValidationRule::factory()->forProvider($provider)->create();

  expect($rule->provider->id)->toBe($provider->id);
});

it('can be attached to many buyers via pivot', function () {
  $rule = LeadQualityValidationRule::factory()->create();
  $buyerA = Integration::factory()->create();
  $buyerB = Integration::factory()->create();

  $rule->buyers()->attach([$buyerA->id, $buyerB->id], ['is_enabled' => true]);

  expect($rule->buyers)->toHaveCount(2);
  expect($buyerA->fresh()->validationRules)->toHaveCount(1);
});

it('scope active filters by status and is_enabled', function () {
  LeadQualityValidationRule::factory()->create();
  LeadQualityValidationRule::factory()->draft()->create();
  LeadQualityValidationRule::factory()->inactive()->create();

  expect(LeadQualityValidationRule::active()->count())->toBe(1);
});

it('scope forIntegration filters by pivot is_enabled', function () {
  $rule = LeadQualityValidationRule::factory()->create();
  $buyer = Integration::factory()->create();
  $rule->buyers()->attach($buyer->id, ['is_enabled' => true]);

  $otherBuyer = Integration::factory()->create();
  $otherRule = LeadQualityValidationRule::factory()->create();
  $otherRule->buyers()->attach($otherBuyer->id, ['is_enabled' => false]);

  expect(LeadQualityValidationRule::forIntegration($buyer->id)->count())->toBe(1);
  expect(LeadQualityValidationRule::forIntegration($otherBuyer->id)->count())->toBe(0);
});

it('exposes settings helpers with defaults', function () {
  $rule = LeadQualityValidationRule::factory()->create([
    'settings' => ['validity_window' => 30, 'max_attempts' => 5, 'ttl' => 300],
  ]);

  expect($rule->validityWindowMinutes())->toBe(30);
  expect($rule->maxAttempts())->toBe(5);
  expect($rule->ttlSeconds())->toBe(300);
});

it('returns async flag from validation_type', function () {
  $otp = LeadQualityValidationRule::factory()->create();
  $lookup = LeadQualityValidationRule::factory()->phoneLookup()->create();

  expect($otp->isAsync())->toBeTrue();
  expect($lookup->isAsync())->toBeFalse();
});
