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
  $data = $response->viewData('page')['props']['data'];

  expect($data['using_defaults'])->toBeTrue();
  expect($data['descriptors'])->toHaveCount(10);

  $byKey = collect($data['descriptors'])->keyBy('key');
  expect($byKey)->toHaveKeys([
    'meta:fingerprint',
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
  $descriptors = $response->viewData('page')['props']['data']['descriptors'];

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
  $meta = $response->viewData('page')['props']['meta'];
  expect($meta['total'])->toBe(2);
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

  $row = collect($response->viewData('page')['props']['rows']['data'])->firstWhere('id', $lead->id);
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

  $row = collect($response->viewData('page')['props']['rows']['data'])->firstWhere('id', $lead->id);
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
  expect(collect($props['data']['versions'])->pluck('id')->all())->toEqualCanonicalizing([$versionA->id, $versionB->id]);

  $rows = collect($props['rows']['data'])->keyBy('id');
  expect($rows[$leadA->id]['version']['path'])->toBe('/offers/');
  expect($rows[$leadB->id]['version']['path'])->toBe('/quote/v2');
  expect($rows[$leadNoVersion->id]['version'])->toBeNull();
});

it('filters leads by version when the version filter is set', function () {
  $landingPage = LandingPage::factory()->create();
  $versionA = LandingPageVersion::factory()->create(['landing_page_id' => $landingPage->id]);
  $versionB = LandingPageVersion::factory()->create(['landing_page_id' => $landingPage->id]);

  attachLeadToLanding($landingPage, $versionA);
  attachLeadToLanding($landingPage, $versionA);
  attachLeadToLanding($landingPage, $versionB);

  $filtersJson = json_encode([['id' => 'version', 'value' => [$versionA->id]]]);
  $response = actingAs($this->user)->get(route('landing_pages.leads', $landingPage->id) . '?filters=' . urlencode($filtersJson));

  $response->assertOk();
  $props = $response->viewData('page')['props'];
  expect($props['meta']['total'])->toBe(2);
  expect($props['data']['selected_versions'])->toBe([$versionA->id]);
});

it('filters leads by device_type, state and os using latest traffic columns', function () {
  $landingPage = LandingPage::factory()->create();

  $desktopUS = attachLeadToLanding($landingPage, null, ['device_type' => 'desktop', 'state' => 'CA', 'os' => 'Windows NT 10.0']);
  $mobileUS = attachLeadToLanding($landingPage, null, ['device_type' => 'mobile', 'state' => 'CA', 'os' => 'iOS 17.5']);
  $desktopOther = attachLeadToLanding($landingPage, null, ['device_type' => 'desktop', 'state' => 'NY', 'os' => 'Linux']);

  $filtersJson = json_encode([['id' => 'device_type', 'value' => ['desktop']]]);
  $response = actingAs($this->user)->get(route('landing_pages.leads', $landingPage->id) . '?filters=' . urlencode($filtersJson));
  expect($response->viewData('page')['props']['meta']['total'])->toBe(2);

  $filtersJson = json_encode([['id' => 'state', 'value' => ['CA']]]);
  $response = actingAs($this->user)->get(route('landing_pages.leads', $landingPage->id) . '?filters=' . urlencode($filtersJson));
  expect($response->viewData('page')['props']['meta']['total'])->toBe(2);

  // OS uses LIKE matching so "Windows" matches "Windows NT 10.0".
  $filtersJson = json_encode([['id' => 'os', 'value' => ['Windows']]]);
  $response = actingAs($this->user)->get(route('landing_pages.leads', $landingPage->id) . '?filters=' . urlencode($filtersJson));
  $rows = $response->viewData('page')['props']['rows']['data'];
  expect(collect($rows)->pluck('id')->all())->toBe([$desktopUS->id]);
});

it('exposes static filter_options catalogs to the frontend', function () {
  $landingPage = LandingPage::factory()->create();

  $response = actingAs($this->user)->get(route('landing_pages.leads', $landingPage->id));
  $options = $response->viewData('page')['props']['data']['filter_options'];

  expect(collect($options['device_type'])->pluck('value')->all())->toEqualCanonicalizing(['mobile', 'desktop']);
  expect(collect($options['state'])->pluck('value')->all())->toContain('CA', 'NY', 'TX');
  expect(collect($options['os'])->pluck('value')->all())->toEqualCanonicalizing(['Windows', 'Mac', 'iOS', 'Android', 'Linux']);
});

it('paginates with a default size of 25 sorted by created_at desc', function () {
  $landingPage = LandingPage::factory()->create();

  $first = attachLeadToLanding($landingPage);
  $first->update(['created_at' => now()->subDays(5)]);

  $newest = attachLeadToLanding($landingPage);
  $newest->update(['created_at' => now()]);

  $response = actingAs($this->user)->get(route('landing_pages.leads', $landingPage->id));

  $props = $response->viewData('page')['props'];
  expect($props['meta']['per_page'])->toBe(25);
  $rows = $props['rows']['data'];
  expect($rows[0]['id'])->toBe($newest->id);
  expect($rows[count($rows) - 1]['id'])->toBe($first->id);
});
