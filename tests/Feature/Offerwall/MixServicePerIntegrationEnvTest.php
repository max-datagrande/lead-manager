<?php

use App\Models\Integration;
use App\Models\Lead;
use App\Models\OfferwallMix;
use App\Models\User;
use App\Services\Offerwall\MixService;
use Illuminate\Support\Facades\Http;

/**
 * Regresion: cuando un mix tiene 2+ integrations con response configs distintos
 * (distinto offer_list_path y/o mapping), MixService::fetchAndAggregateOffers
 * debe parsear cada response con SU propio IntegrationEnvironment, no con el
 * de la ultima integration iterada.
 *
 * Bug original: $prodEnv quedaba "sticky" del primer loop y se reusaba en el
 * segundo loop para todas las integrations, causando que la primera devolviera
 * [] al buscar offer_list_path en el JSON equivocado.
 */
describe('MixService aggregation with heterogeneous parser configs', function () {
  it('parses each integration response with its own environment config', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Integration A: offer_list_path = "result", mapping con keys tipo transparent.ly
    $integrationA = Integration::factory()->create(['type' => 'offerwall', 'name' => 'Vendor A']);
    $envA = $integrationA->environments()->create([
      'env_type' => 'offerwall',
      'environment' => 'production',
      'url' => 'https://vendor-a.example.com/offers',
      'method' => 'POST',
      'request_headers' => '{}',
      'request_body' => '{}',
    ]);
    $envA->offerwallResponseConfig()->create([
      'offer_list_path' => 'result',
      'mapping' => [
        'cpc' => 'publisherRevenue',
        'title' => 'title',
        'company' => 'brandName',
        'logo_url' => 'logoUrl',
        'click_url' => 'clickUrl',
        'description' => 'bulletedDescription',
        'display_name' => 'brandName',
        'impression_url' => null,
      ],
      'fallbacks' => null,
    ]);

    // Integration B: offer_list_path = "ads", mapping con keys tipo MediaAlpha
    $integrationB = Integration::factory()->create(['type' => 'offerwall', 'name' => 'Vendor B']);
    $envB = $integrationB->environments()->create([
      'env_type' => 'offerwall',
      'environment' => 'production',
      'url' => 'https://vendor-b.example.com/ads',
      'method' => 'POST',
      'request_headers' => '{}',
      'request_body' => '{}',
    ]);
    $envB->offerwallResponseConfig()->create([
      'offer_list_path' => 'ads',
      'mapping' => [
        'cpc' => 'bid',
        'title' => 'headline',
        'company' => 'carrier',
        'logo_url' => 'medium_image_url',
        'click_url' => 'click_url',
        'description' => 'description',
        'display_name' => 'carrier',
        'impression_url' => 'call_url',
      ],
      'fallbacks' => null,
    ]);

    $lead = Lead::factory()->create(['fingerprint' => 'fp-regression-test']);

    $mix = OfferwallMix::create([
      'name' => 'Regression Mix',
      'is_active' => true,
    ]);
    $mix->integrations()->attach([$integrationA->id, $integrationB->id]);

    Http::fake([
      'vendor-a.example.com/*' => Http::response(
        [
          'result' => [
            [
              'title' => 'Smart Auto',
              'brandName' => 'Smart',
              'logoUrl' => 'https://cdn/a.png',
              'clickUrl' => 'https://click/a',
              'publisherRevenue' => 1.5,
              'bulletedDescription' => ['Save up to $500'],
            ],
          ],
        ],
        200,
      ),
      'vendor-b.example.com/*' => Http::response(
        [
          'num_ads' => 1,
          'ads' => [
            [
              'headline' => 'MediaAlpha Auto',
              'carrier' => 'Geico',
              'medium_image_url' => 'https://cdn/b.png',
              'click_url' => 'https://click/b',
              'bid' => 2.25,
              'description' => 'Quote in 2 minutes',
              'call_url' => 'tel:+18005551234',
            ],
          ],
        ],
        200,
      ),
    ]);

    $service = app(MixService::class);
    $result = $service->fetchAndAggregateOffers($mix->fresh(), $lead->fingerprint);

    expect($result['success'])->toBeTrue()->and($result['meta']['total_offers'])->toBe(2)->and($result['meta']['successful_integrations'])->toBe(2);

    // Cada integration aporto su oferta — sin el fix, A devolvia [] al buscar "ads" en su JSON.
    $byIntegration = collect($result['data'])->keyBy('integration_id');

    expect($byIntegration)->toHaveKey($integrationA->id)->and($byIntegration)->toHaveKey($integrationB->id);

    // La oferta A se mapeo con el config de A (no con el de B).
    $offerA = $byIntegration[$integrationA->id];
    expect($offerA['title'])->toBe('Smart Auto')->and($offerA['company'])->toBe('Smart')->and((float) $offerA['cpc'])->toBe(1.5);

    // La oferta B se mapeo con el config de B.
    $offerB = $byIntegration[$integrationB->id];
    expect($offerB['title'])
      ->toBe('MediaAlpha Auto')
      ->and($offerB['company'])
      ->toBe('Geico')
      ->and((float) $offerB['cpc'])
      ->toBe(2.25)
      ->and($offerB['impression_url'])
      ->toBe('tel:+18005551234');
  });
});
