<?php

use App\Models\Field;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\OfferwallMix;
use App\Models\User;
use App\Services\Offerwall\MixService;
use App\Services\PayloadProcessorService;
use Illuminate\Support\Facades\Http;

/**
 * Regresion: el refactor cc1fdb7 dejo MixService::logIntegrationCall persistiendo
 * original_field_values / mapped_field_values en null hardcodeado. Como
 * ConversionService arma tracked_fields leyendo esas columnas, las offerwall
 * conversions quedaban con todos los valores en null (keys presentes, values null).
 *
 * Estos tests cubren el fix: MixService vuelve a poblar ambos arrays via
 * PayloadProcessorService::buildFieldValueMaps, y la conversion resultante recupera
 * sus tracked_fields con valores reales.
 */

/**
 * Crea integration offerwall + env de produccion con un parser config minimo
 * y los tokenMappings/fields necesarios para los tracked_fields.
 */
function makeTrackedFieldsIntegration(): Integration
{
  $cptype = Field::factory()->create(['name' => 'cptype']);
  $state = Field::factory()->create(['name' => 'state']);
  $zip = Field::factory()->create(['name' => 'zip_code']);

  $integration = Integration::factory()->create(['type' => 'offerwall', 'name' => 'Tracked Vendor']);
  $env = $integration->environments()->create([
    'env_type' => 'offerwall',
    'environment' => 'production',
    'url' => 'https://tracked-vendor.example.com/offers',
    'method' => 'POST',
    'request_headers' => '{}',
    'request_body' => '{}',
  ]);
  $env->offerwallResponseConfig()->create([
    'offer_list_path' => 'result',
    'mapping' => [
      'cpc' => 'rev',
      'title' => 'title',
      'company' => 'brand',
    ],
    'fallbacks' => null,
  ]);

  // cptype: original "web" -> mapped "PLACEMENT_99"
  $integration->tokenMappings()->create([
    'field_id' => $cptype->id,
    'data_type' => 'string',
    'default_value' => null,
    'value_mapping' => ['web' => 'PLACEMENT_99'],
  ]);
  // state: sin value_mapping (original == mapped)
  $integration->tokenMappings()->create([
    'field_id' => $state->id,
    'data_type' => 'string',
    'default_value' => null,
    'value_mapping' => null,
  ]);
  // zip_code: original "90001" -> mapped "ZIP_MAP"
  $integration->tokenMappings()->create([
    'field_id' => $zip->id,
    'data_type' => 'string',
    'default_value' => null,
    'value_mapping' => ['90001' => 'ZIP_MAP'],
  ]);

  return $integration->load('tokenMappings.field', 'environments.fieldHashes');
}

function attachTrackedLeadResponses(Lead $lead, Integration $integration): void
{
  $byName = $integration->tokenMappings->keyBy(fn($m) => $m->field->name);
  $values = ['cptype' => 'web', 'state' => 'CA', 'zip_code' => '90001'];
  foreach ($values as $name => $value) {
    $lead->leadFieldResponses()->create([
      'field_id' => $byName[$name]->field_id,
      'value' => $value,
      'fingerprint' => $lead->fingerprint,
    ]);
  }
}

describe('Offerwall tracked_fields population', function () {
  it('buildFieldValueMaps separates original and mapped values keyed by field name', function () {
    $integration = makeTrackedFieldsIntegration();
    $leadData = ['cptype' => 'web', 'state' => 'CA', 'zip_code' => '90001'];

    $maps = (new PayloadProcessorService())->buildFieldValueMaps($integration, $integration->environments->first(), $leadData);

    expect($maps['originalValues'])
      ->toBe(['cptype' => 'web', 'state' => 'CA', 'zip_code' => '90001'])
      ->and($maps['mappedValues'])
      ->toBe(['cptype' => 'PLACEMENT_99', 'state' => 'CA', 'zip_code' => 'ZIP_MAP']);
  });

  it('MixService persists original/mapped field values on the IntegrationCallLog', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $integration = makeTrackedFieldsIntegration();
    $lead = Lead::factory()->create(['fingerprint' => 'fp-tracked-fields']);
    attachTrackedLeadResponses($lead, $integration);

    $mix = OfferwallMix::create(['name' => 'Tracked Mix', 'is_active' => true]);
    $mix->integrations()->attach($integration->id);

    Http::fake([
      'tracked-vendor.example.com/*' => Http::response(['result' => [['title' => 'Offer One', 'brand' => 'Acme', 'rev' => 1.0]]], 200),
    ]);

    app(MixService::class)->fetchAndAggregateOffers($mix->fresh(), $lead->fingerprint);

    $callLog = \App\Models\IntegrationCallLog::where('integration_id', $integration->id)->latest('id')->first();

    expect($callLog->original_field_values)
      ->toBe(['cptype' => 'web', 'state' => 'CA', 'zip_code' => '90001'])
      ->and($callLog->mapped_field_values)
      ->toBe(['cptype' => 'PLACEMENT_99', 'state' => 'CA', 'zip_code' => 'ZIP_MAP']);
  });

  /**
   * Cobertura del shape final de tracked_fields que arma ConversionService a partir
   * de los arrays del call log. Se invoca el mismo mapeo deliberado de
   * ConversionService::createConversion (cptype=original, placement_id=mapped[cptype],
   * state=original, zip_code=mapped) sin pasar por el insert de offerwall_conversions,
   * que en el test DB choca con la columna company_id NOT NULL (artefacto SQLite: la
   * migration remove_company_id es no-op en sqlite, en produccion la columna no existe).
   */
  it('builds the intentional tracked_fields shape from a populated call log', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $integration = makeTrackedFieldsIntegration();
    $lead = Lead::factory()->create(['fingerprint' => 'fp-tracked-shape']);
    attachTrackedLeadResponses($lead, $integration);

    $mix = OfferwallMix::create(['name' => 'Tracked Shape Mix', 'is_active' => true]);
    $mix->integrations()->attach($integration->id);

    Http::fake([
      'tracked-vendor.example.com/*' => Http::response(['result' => [['title' => 'Offer One', 'brand' => 'Acme', 'rev' => 1.0]]], 200),
    ]);

    app(MixService::class)->fetchAndAggregateOffers($mix->fresh(), $lead->fingerprint);

    $callLog = \App\Models\IntegrationCallLog::where('integration_id', $integration->id)->latest('id')->first();

    // Espeja el mapeo de ConversionService::createConversion (intencional).
    $trackedFields = [
      'cptype' => $callLog->original_field_values['cptype'] ?? null,
      'placement_id' => $callLog->mapped_field_values['cptype'] ?? null,
      'state' => $callLog->original_field_values['state'] ?? null,
      'zip_code' => $callLog->mapped_field_values['zip_code'] ?? null,
    ];

    expect($trackedFields)->toBe([
      'cptype' => 'web', // original
      'placement_id' => 'PLACEMENT_99', // mapped value of cptype (intentional)
      'state' => 'CA', // original
      'zip_code' => 'ZIP_MAP', // mapped
    ]);
  });
});
