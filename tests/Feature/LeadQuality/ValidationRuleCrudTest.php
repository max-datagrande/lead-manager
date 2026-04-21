<?php

use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationType;
use App\Models\Integration;
use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationRule;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeEach(function () {
  $this->admin = User::factory()->create(['role' => 'admin']);
});

test('admin sees the rules index page with provider and buyer summaries', function () {
  $rule = LeadQualityValidationRule::factory()->create();
  $buyers = Integration::factory()->count(2)->create();
  $rule->buyers()->attach($buyers->pluck('id')->all(), ['is_enabled' => true]);

  $response = actingAs($this->admin)->get(route('lead-quality.validation-rules.index'));

  $response->assertSuccessful();
  $response->assertInertia(
    fn(AssertableInertia $page) => $page
      ->component('lead-quality/validation-rules/index')
      ->has('rules', 1)
      ->where('rules.0.buyers_count', 2)
      ->where('rules.0.provider.is_usable', true),
  );
});

test('admin sees create page with provider options, buyers, and validation types', function () {
  LeadQualityProvider::factory()->count(2)->create();
  Integration::factory()->count(3)->create();

  $response = actingAs($this->admin)->get(route('lead-quality.validation-rules.create'));

  $response->assertSuccessful();
  $response->assertInertia(
    fn(AssertableInertia $page) => $page
      ->component('lead-quality/validation-rules/create')
      ->has('validation_types', count(ValidationType::cases()))
      ->has('statuses', count(RuleStatus::cases()))
      ->has('providers', 2)
      ->has('buyers', 3),
  );
});

test('store creates a rule and attaches selected buyers via pivot', function () {
  $provider = LeadQualityProvider::factory()->create();
  $buyers = Integration::factory()->count(3)->create();

  $response = actingAs($this->admin)->post(route('lead-quality.validation-rules.store'), [
    'name' => 'Premium OTP',
    'validation_type' => ValidationType::OTP_PHONE->value,
    'provider_id' => $provider->id,
    'status' => RuleStatus::ACTIVE->value,
    'is_enabled' => true,
    'settings' => ['channel' => 'sms', 'otp_length' => 6, 'ttl' => 600, 'max_attempts' => 3, 'validity_window' => 15],
    'priority' => 50,
    'buyer_ids' => [$buyers[0]->id, $buyers[2]->id],
  ]);

  $response->assertRedirect(route('lead-quality.validation-rules.index'));

  $rule = LeadQualityValidationRule::firstWhere('name', 'Premium OTP');
  expect($rule)->not->toBeNull();
  expect($rule->slug)->toBe('premium-otp');
  expect($rule->buyers()->pluck('integrations.id')->sort()->values()->all())->toBe([$buyers[0]->id, $buyers[2]->id]);
  expect($rule->buyers->first()->pivot->is_enabled)->toBe(1);
});

test('store rejects unknown buyer, invalid validation_type, or missing provider', function () {
  $provider = LeadQualityProvider::factory()->create();

  $response = actingAs($this->admin)->post(route('lead-quality.validation-rules.store'), [
    'name' => 'Broken',
    'validation_type' => 'nonsense',
    'provider_id' => 999999,
    'status' => RuleStatus::DRAFT->value,
    'buyer_ids' => [999999],
  ]);

  $response->assertSessionHasErrors(['validation_type', 'provider_id', 'buyer_ids.0']);
  expect(LeadQualityValidationRule::count())->toBe(0);

  // Sanity: with correct IDs it passes.
  $buyer = Integration::factory()->create();
  actingAs($this->admin)
    ->post(route('lead-quality.validation-rules.store'), [
      'name' => 'Fine',
      'validation_type' => ValidationType::OTP_PHONE->value,
      'provider_id' => $provider->id,
      'status' => RuleStatus::DRAFT->value,
      'buyer_ids' => [$buyer->id],
    ])
    ->assertRedirect();
});

test('update syncs pivot removing and adding buyers', function () {
  $rule = LeadQualityValidationRule::factory()->create();
  $buyers = Integration::factory()->count(3)->create();
  $rule->buyers()->attach([$buyers[0]->id, $buyers[1]->id], ['is_enabled' => true]);

  $response = actingAs($this->admin)->put(route('lead-quality.validation-rules.update', $rule), [
    'name' => $rule->name,
    'validation_type' => $rule->validation_type->value,
    'provider_id' => $rule->provider_id,
    'status' => RuleStatus::INACTIVE->value,
    'is_enabled' => false,
    'priority' => 200,
    'buyer_ids' => [$buyers[1]->id, $buyers[2]->id],
  ]);

  $response->assertRedirect(route('lead-quality.validation-rules.index'));

  $rule->refresh()->load('buyers');
  expect($rule->status)->toBe(RuleStatus::INACTIVE);
  expect($rule->priority)->toBe(200);
  expect($rule->buyers->pluck('id')->sort()->values()->all())->toBe([$buyers[1]->id, $buyers[2]->id]);
});

test('destroy deletes the rule and cascades the pivot', function () {
  $rule = LeadQualityValidationRule::factory()->create();
  $buyer = Integration::factory()->create();
  $rule->buyers()->attach($buyer->id, ['is_enabled' => true]);

  actingAs($this->admin)->delete(route('lead-quality.validation-rules.destroy', $rule))->assertRedirect();

  expect(LeadQualityValidationRule::find($rule->id))->toBeNull();
  expect(\DB::table('buyer_validation_rule')->where('validation_rule_id', $rule->id)->count())->toBe(0);
});

test('edit page exposes current buyer_ids and settings for prefill', function () {
  $rule = LeadQualityValidationRule::factory()
    ->phoneLookup()
    ->create([
      'settings' => ['sync_check' => true, 'validity_window' => 30, 'required_score' => 80],
    ]);
  $buyer = Integration::factory()->create();
  $rule->buyers()->attach($buyer->id, ['is_enabled' => true]);

  $response = actingAs($this->admin)->get(route('lead-quality.validation-rules.edit', $rule));

  $response->assertSuccessful();
  $response->assertInertia(
    fn(AssertableInertia $page) => $page
      ->component('lead-quality/validation-rules/edit')
      ->where('rule.id', $rule->id)
      ->where('rule.settings.validity_window', 30)
      ->where('rule.settings.required_score', 80)
      ->where('rule.buyer_ids', [$buyer->id]),
  );
});

test('non-admin cannot access rule routes', function () {
  $user = User::factory()->create(['role' => 'user']);

  actingAs($user)->get(route('lead-quality.validation-rules.index'))->assertForbidden();
});

dataset('validation_types_dataset', [
  'otp_phone' => ['otp_phone'],
  'otp_email' => ['otp_email'],
  'phone_lookup' => ['phone_lookup'],
  'email_reputation' => ['email_reputation'],
  'ipqs_score' => ['ipqs_score'],
]);

test('store accepts every supported validation_type', function (string $type) {
  $provider = LeadQualityProvider::factory()->create();
  $buyer = Integration::factory()->create();

  actingAs($this->admin)
    ->post(route('lead-quality.validation-rules.store'), [
      'name' => "Rule {$type}",
      'validation_type' => $type,
      'provider_id' => $provider->id,
      'status' => RuleStatus::DRAFT->value,
      'buyer_ids' => [$buyer->id],
    ])
    ->assertRedirect();

  expect(LeadQualityValidationRule::where('validation_type', $type)->exists())->toBeTrue();
})->with('validation_types_dataset');
