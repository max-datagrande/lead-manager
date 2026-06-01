<?php

use App\Models\Field;
use App\Models\LandingPage;
use App\Models\LandingPageVersion;
use App\Models\Lead;
use App\Models\LeadFieldResponse;
use App\Models\TrafficLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;

beforeEach(function () {
  Cache::forget('internal_postback_tokens');
  $this->user = User::factory()->create();
  actingAs($this->user);
});

function attachLeadToLanding(LandingPage $landingPage, ?LandingPageVersion $version = null, array $trafficOverrides = []): Lead
{
  $fingerprint = hash('sha256', uniqid('lead-', true));
  $lead = Lead::factory()->create(['fingerprint' => $fingerprint]);

  TrafficLog::factory()->create(
    array_merge(
      [
        'fingerprint' => $fingerprint,
        'landing_id' => $landingPage->id,
        'landing_page_version_id' => $version?->id,
        'visit_date' => now()->toDateString(),
      ],
      $trafficOverrides,
    ),
  );

  return $lead;
}

it('returns baseline descriptors when the landing has no columns configured', function () {
  $landingPage = LandingPage::factory()->create();

  $response = actingAs($this->user)->get(route('landing_pages.leads', $landingPage->id));

  $response->assertOk();
  $props = $response->viewData('page')['props'];

  expect($props['using_defaults'])->toBeTrue();
  expect($props['descriptors'])->toHaveCount(10);

  $byKey = collect($props['descriptors'])->keyBy('key');
  expect($byKey)->toHaveKeys([
    'meta:id',
    'meta:created_at',
    'traffic:postal_code',
    'traffic:ip_address',
    'traffic:state',
    'traffic:city',
    'traffic:browser',
    'traffic:os',
    'traffic:device_type',
    'traffic:referrer',
  ]);
  expect($byKey['traffic:postal_code']['label'])->toBe('Geo Postal Code');
  expect($byKey['traffic:browser']['label'])->toBe('Browser');
  expect($byKey['traffic:os']['label'])->toBe('OS');
});

it('returns configured descriptors using the column configuration', function () {
  $field = Field::factory()->create(['name' => 'email', 'label' => 'Email Address']);
  $landingPage = LandingPage::factory()->create();
  $landingPage
    ->columns()
    ->createMany([
      ['source' => 'field', 'reference' => (string) $field->id],
      ['source' => 'traffic', 'reference' => 's10'],
      ['source' => 'traffic', 'reference' => 'ip_address'],
    ]);

  $response = actingAs($this->user)->get(route('landing_pages.leads', $landingPage->id));

  $response->assertOk();
  $descriptors = $response->viewData('page')['props']['descriptors'];

  expect($descriptors)->toHaveCount(3);
  expect(collect($descriptors)->pluck('key')->all())->toEqualCanonicalizing(['field:' . $field->id, 'traffic:s10', 'traffic:ip_address']);
  $fieldDescriptor = collect($descriptors)->firstWhere('key', 'field:' . $field->id);
  expect($fieldDescriptor['label'])->toBe('Email Address');
  expect($fieldDescriptor['reference'])->toBe((string) $field->id);

  $trafficDescriptor = collect($descriptors)->firstWhere('key', 'traffic:ip_address');
  expect($trafficDescriptor['label'])->toBe('Geo IP Address');
});

it('only returns leads whose traffic logs match the landing', function () {
  $landingA = LandingPage::factory()->create();
  $landingB = LandingPage::factory()->create();

  attachLeadToLanding($landingA);
  attachLeadToLanding($landingA);
  attachLeadToLanding($landingB);

  $response = actingAs($this->user)->get(route('landing_pages.leads', $landingA->id));

  $response->assertOk();
  $leads = $response->viewData('page')['props']['leads'];
  expect($leads['total'])->toBe(2);
});

it('resolves field-source values from lead_field_responses', function () {
  $field = Field::factory()->create(['name' => 'email', 'label' => 'Email']);
  $landingPage = LandingPage::factory()->create();
  $landingPage->columns()->create(['source' => 'field', 'reference' => (string) $field->id]);

  $lead = attachLeadToLanding($landingPage);
  LeadFieldResponse::query()->create([
    'lead_id' => $lead->id,
    'field_id' => $field->id,
    'value' => 'jane@example.com',
    'fingerprint' => $lead->fingerprint,
  ]);

  $response = actingAs($this->user)->get(route('landing_pages.leads', $landingPage->id));

  $row = collect($response->viewData('page')['props']['leads']['data'])->firstWhere('id', $lead->id);
  expect($row['values']['field:' . $field->id])->toBe('jane@example.com');
});

it('resolves traffic-source values from the latest traffic log', function () {
  $landingPage = LandingPage::factory()->create();
  $landingPage->columns()->create(['source' => 'traffic', 'reference' => 'postal_code']);

  $lead = attachLeadToLanding($landingPage, null, [
    'postal_code' => '90210',
    'ip_address' => '8.8.8.8',
  ]);

  $response = actingAs($this->user)->get(route('landing_pages.leads', $landingPage->id));

  $row = collect($response->viewData('page')['props']['leads']['data'])->firstWhere('id', $lead->id);
  expect($row['values']['traffic:postal_code'])->toBe('90210');
});

it('exposes the version catalog of the landing and attaches version per row', function () {
  $landingPage = LandingPage::factory()->create();
  $versionA = LandingPageVersion::factory()->create(['landing_page_id' => $landingPage->id, 'path' => '/offers/']);
  $versionB = LandingPageVersion::factory()->create(['landing_page_id' => $landingPage->id, 'path' => '/quote/v2']);

  $leadA = attachLeadToLanding($landingPage, $versionA);
  $leadB = attachLeadToLanding($landingPage, $versionB);
  $leadNoVersion = attachLeadToLanding($landingPage, null);

  $response = actingAs($this->user)->get(route('landing_pages.leads', $landingPage->id));

  $props = $response->viewData('page')['props'];
  expect(collect($props['versions'])->pluck('id')->all())->toEqualCanonicalizing([$versionA->id, $versionB->id]);

  $rows = collect($props['leads']['data'])->keyBy('id');
  expect($rows[$leadA->id]['version']['path'])->toBe('/offers/');
  expect($rows[$leadB->id]['version']['path'])->toBe('/quote/v2');
  expect($rows[$leadNoVersion->id]['version'])->toBeNull();
});

it('filters leads by version when ?version is provided', function () {
  $landingPage = LandingPage::factory()->create();
  $versionA = LandingPageVersion::factory()->create(['landing_page_id' => $landingPage->id]);
  $versionB = LandingPageVersion::factory()->create(['landing_page_id' => $landingPage->id]);

  attachLeadToLanding($landingPage, $versionA);
  attachLeadToLanding($landingPage, $versionA);
  attachLeadToLanding($landingPage, $versionB);

  $response = actingAs($this->user)->get(route('landing_pages.leads', $landingPage->id) . '?version=' . $versionA->id);

  $response->assertOk();
  $props = $response->viewData('page')['props'];
  expect($props['leads']['total'])->toBe(2);
  expect($props['selected_version'])->toBe($versionA->id);
});

it('paginates with a default size of 25 sorted by created_at desc', function () {
  $landingPage = LandingPage::factory()->create();

  $first = attachLeadToLanding($landingPage);
  $first->update(['created_at' => now()->subDays(5)]);

  $newest = attachLeadToLanding($landingPage);
  $newest->update(['created_at' => now()]);

  $response = actingAs($this->user)->get(route('landing_pages.leads', $landingPage->id));

  $props = $response->viewData('page')['props'];
  expect($props['leads']['per_page'])->toBe(25);
  $rows = $props['leads']['data'];
  expect($rows[0]['id'])->toBe($newest->id);
  expect($rows[count($rows) - 1]['id'])->toBe($first->id);
});
