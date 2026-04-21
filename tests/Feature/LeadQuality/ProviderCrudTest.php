<?php

use App\Enums\LeadQuality\LeadQualityProviderType;
use App\Enums\LeadQuality\ProviderStatus;
use App\Models\LeadQualityProvider;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeEach(function () {
  $this->admin = User::factory()->create(['role' => 'admin']);
});

test('admin can see providers index page', function () {
  LeadQualityProvider::factory()->count(2)->create();

  $response = actingAs($this->admin)->get(route('lead-quality.providers.index'));

  $response->assertSuccessful();
  $response->assertInertia(
    fn(AssertableInertia $page) => $page->component('lead-quality/providers/index')->has('providers', 2)->has('provider_types')->has('statuses'),
  );
});

test('admin can see create page with provider types', function () {
  $response = actingAs($this->admin)->get(route('lead-quality.providers.create'));

  $response->assertSuccessful();
  $response->assertInertia(
    fn(AssertableInertia $page) => $page->component('lead-quality/providers/create')->has('provider_types')->has('statuses')->has('environments', 3),
  );
});

test('admin can create a provider with encrypted credentials', function () {
  $payload = [
    'name' => 'Twilio Verify - Prod',
    'type' => LeadQualityProviderType::TWILIO_VERIFY->value,
    'status' => ProviderStatus::ACTIVE->value,
    'is_enabled' => true,
    'environment' => 'production',
    'credentials' => [
      'account_sid' => 'AC' . str_repeat('a', 32),
      'auth_token' => 'secret-token-abcd',
      'verify_service_sid' => 'VA' . str_repeat('b', 32),
    ],
    'settings' => ['default_channel' => 'sms'],
    'notes' => null,
  ];

  $response = actingAs($this->admin)->post(route('lead-quality.providers.store'), $payload);

  $response->assertRedirect(route('lead-quality.providers.index'));

  $provider = LeadQualityProvider::firstWhere('name', 'Twilio Verify - Prod');
  expect($provider)->not->toBeNull();
  expect($provider->credentials['auth_token'])->toBe('secret-token-abcd');

  $raw = \DB::table('lead_quality_providers')->where('id', $provider->id)->value('credentials');
  expect($raw)->not->toContain('secret-token-abcd');
});

test('store rejects invalid type and duplicate name', function () {
  LeadQualityProvider::factory()->create(['name' => 'Taken Name']);

  $response = actingAs($this->admin)->post(route('lead-quality.providers.store'), [
    'name' => 'Taken Name',
    'type' => 'unknown_provider',
    'status' => ProviderStatus::ACTIVE->value,
    'environment' => 'production',
  ]);

  $response->assertSessionHasErrors(['name', 'type']);
});

test('admin can see edit page', function () {
  $provider = LeadQualityProvider::factory()->create();

  $response = actingAs($this->admin)->get(route('lead-quality.providers.edit', $provider));

  $response->assertSuccessful();
  $response->assertInertia(fn(AssertableInertia $page) => $page->component('lead-quality/providers/edit')->where('provider.id', $provider->id));
});

test('update merges credentials with existing ones', function () {
  $provider = LeadQualityProvider::factory()->create([
    'credentials' => [
      'account_sid' => 'AC-original',
      'auth_token' => 'token-original',
      'verify_service_sid' => 'VA-original',
    ],
  ]);

  $response = actingAs($this->admin)->put(route('lead-quality.providers.update', $provider), [
    'name' => $provider->name,
    'type' => $provider->type->value,
    'status' => ProviderStatus::INACTIVE->value,
    'is_enabled' => false,
    'environment' => 'sandbox',
    // Only update one credential, leave others intact.
    'credentials' => ['auth_token' => 'token-updated'],
    'settings' => $provider->settings ?? [],
  ]);

  $response->assertRedirect(route('lead-quality.providers.index'));

  $provider->refresh();
  expect($provider->credentials['auth_token'])->toBe('token-updated');
  expect($provider->credentials['account_sid'])->toBe('AC-original');
  expect($provider->credentials['verify_service_sid'])->toBe('VA-original');
  expect($provider->status)->toBe(ProviderStatus::INACTIVE);
});

test('admin can delete a provider', function () {
  $provider = LeadQualityProvider::factory()->create();

  $response = actingAs($this->admin)->delete(route('lead-quality.providers.destroy', $provider));

  $response->assertRedirect();
  expect(LeadQualityProvider::find($provider->id))->toBeNull();
});

test('non-admin cannot access provider routes', function () {
  $user = User::factory()->create(['role' => 'user']);

  actingAs($user)->get(route('lead-quality.providers.index'))->assertForbidden();
});
