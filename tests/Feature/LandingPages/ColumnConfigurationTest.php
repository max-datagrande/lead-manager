<?php

use App\Models\Field;
use App\Models\LandingPage;
use App\Models\LandingPageColumn;
use App\Models\User;
use App\Models\Vertical;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;

beforeEach(function () {
  Cache::forget('internal_postback_tokens');
  $this->user = User::factory()->create();
  actingAs($this->user);
});

function landingPayload(array $overrides = []): array
{
  return array_merge(
    [
      'name' => 'Auto Insurance Landing',
      'url' => 'https://example.com/auto-' . uniqid(),
      'is_external' => false,
      'vertical_id' => Vertical::factory()->create()->id,
      'company_id' => null,
      'active' => true,
    ],
    $overrides,
  );
}

it('persists columns of both source types on store', function () {
  $field = Field::factory()->create();

  $payload = landingPayload([
    'columns' => [
      ['source' => 'field', 'reference' => (string) $field->id],
      ['source' => 'traffic', 'reference' => 's10'],
      ['source' => 'traffic', 'reference' => 'ip_address'],
    ],
  ]);

  actingAs($this->user)->post(route('landing_pages.store'), $payload)->assertRedirect(route('landing_pages.index'));

  $landingPage = LandingPage::query()->where('url', $payload['url'])->firstOrFail();

  expect($landingPage->columns)->toHaveCount(3);
  expect($landingPage->columns->pluck('reference')->all())->toContain((string) $field->id, 's10', 'ip_address');
});

it('replaces columns on update (sync semantics)', function () {
  $fieldA = Field::factory()->create();
  $fieldB = Field::factory()->create();

  $landingPage = LandingPage::factory()->create();
  $landingPage->columns()->createMany([['source' => 'field', 'reference' => (string) $fieldA->id], ['source' => 'traffic', 'reference' => 's10']]);

  $payload = landingPayload([
    'name' => $landingPage->name,
    'url' => $landingPage->url,
    'vertical_id' => $landingPage->vertical_id,
    'columns' => [['source' => 'field', 'reference' => (string) $fieldB->id], ['source' => 'traffic', 'reference' => 'country_code']],
  ]);

  actingAs($this->user)
    ->put(route('landing_pages.update', $landingPage->id), $payload)
    ->assertRedirect(route('landing_pages.index'));

  $landingPage->refresh()->load('columns');

  expect($landingPage->columns)->toHaveCount(2);
  expect($landingPage->columns->pluck('reference')->all())->toEqualCanonicalizing([(string) $fieldB->id, 'country_code']);
});

it('rejects field references that do not exist', function () {
  $payload = landingPayload([
    'columns' => [['source' => 'field', 'reference' => '99999']],
  ]);

  actingAs($this->user)->post(route('landing_pages.store'), $payload)->assertSessionHasErrors('columns.0.reference');
});

it('rejects traffic keys outside the whitelist', function () {
  $payload = landingPayload([
    'columns' => [['source' => 'traffic', 'reference' => 'not_a_real_column']],
  ]);

  actingAs($this->user)->post(route('landing_pages.store'), $payload)->assertSessionHasErrors('columns.0.reference');
});

it('rejects unknown source values', function () {
  $payload = landingPayload([
    'columns' => [['source' => 'invalid', 'reference' => 'whatever']],
  ]);

  actingAs($this->user)->post(route('landing_pages.store'), $payload)->assertSessionHasErrors('columns.0.source');
});

it('cascade-deletes columns when the landing page is deleted', function () {
  $field = Field::factory()->create();
  $landingPage = LandingPage::factory()->create();
  $landingPage->columns()->createMany([['source' => 'field', 'reference' => (string) $field->id], ['source' => 'traffic', 'reference' => 's10']]);

  $landingPage->delete();

  expect(LandingPageColumn::query()->where('landing_page_id', $landingPage->id)->count())->toBe(0);
});

it('cleans up orphan field-source columns when the referenced Field is deleted', function () {
  $fieldKeep = Field::factory()->create();
  $fieldDrop = Field::factory()->create();
  $landingPage = LandingPage::factory()->create();
  $landingPage
    ->columns()
    ->createMany([
      ['source' => 'field', 'reference' => (string) $fieldKeep->id],
      ['source' => 'field', 'reference' => (string) $fieldDrop->id],
      ['source' => 'traffic', 'reference' => 's10'],
    ]);

  $fieldDrop->delete();

  $remaining = $landingPage->columns()->get();

  expect($remaining)->toHaveCount(2);
  expect($remaining->pluck('reference')->all())->toEqualCanonicalizing([(string) $fieldKeep->id, 's10']);
});
