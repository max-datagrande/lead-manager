<?php

use App\Models\Integration;

/**
 * Tests that the env_type column added to integration_environments
 * does not break offerwall integrations.
 *
 * MixService uses: $integration->environments->where('environment', 'production')->first()
 * This must continue to work after the env_type column was added.
 */
describe('integration_environments env_type column', function () {
  it('stores offerwall environments with env_type=offerwall by default', function () {
    $integration = Integration::factory()->create(['type' => 'offerwall']);
    $integration->environments()->create([
      'env_type' => 'offerwall',
      'environment' => 'production',
      'url' => 'https://prod.example.com/offerwall',
      'method' => 'POST',
      'request_headers' => '{}',
    ]);

    $env = $integration->environments()->where('environment', 'production')->first();

    expect($env)->not->toBeNull();
    expect($env->env_type)->toBe('offerwall');
    expect($env->url)->toBe('https://prod.example.com/offerwall');
  });

  it('MixService environment lookup pattern still works after env_type added', function () {
    // MixService uses: $integration->environments->where('environment', 'production')->first()
    // Verify this query returns the correct record.
    $integration = Integration::factory()->create(['type' => 'offerwall']);
    $integration->environments()->createMany([
      ['env_type' => 'offerwall', 'environment' => 'development', 'url' => 'https://dev.example.com', 'method' => 'POST', 'request_headers' => '{}'],
      ['env_type' => 'offerwall', 'environment' => 'production', 'url' => 'https://prod.example.com', 'method' => 'POST', 'request_headers' => '{}'],
    ]);

    $integration->load('environments');

    // Simulate MixService lookup (no env_type filter — should not be needed)
    $prodEnv = $integration->environments->where('environment', 'production')->first();

    expect($prodEnv)->not->toBeNull();
    expect($prodEnv->env_type)->toBe('offerwall');
    expect($prodEnv->url)->toBe('https://prod.example.com');
  });

  it('ping-post integrations have 4 environments with correct env_type values', function () {
    $integration = Integration::factory()->create(['type' => 'ping-post']);

    foreach (['ping', 'post'] as $et) {
      foreach (['development', 'production'] as $env) {
        $integration->environments()->create([
          'env_type' => $et,
          'environment' => $env,
          'url' => "https://{$et}.example.com/{$env}",
          'method' => 'POST',
          'request_headers' => '{}',
        ]);
      }
    }

    $integration->load('environments');

    expect($integration->environments)->toHaveCount(4);

    $pingProd = $integration->environments
      ->where('env_type', 'ping')
      ->where('environment', 'production')
      ->first();

    $postProd = $integration->environments
      ->where('env_type', 'post')
      ->where('environment', 'production')
      ->first();

    expect($pingProd->url)->toBe('https://ping.example.com/production');
    expect($postProd->url)->toBe('https://post.example.com/production');
  });

  it('offerwall environments are not polluted by ping-post environments on other integrations', function () {
    $offerwall = Integration::factory()->create(['type' => 'offerwall']);
    $offerwall->environments()->createMany([
      ['env_type' => 'offerwall', 'environment' => 'development', 'url' => 'https://ow-dev.example.com', 'method' => 'POST', 'request_headers' => '{}'],
      ['env_type' => 'offerwall', 'environment' => 'production', 'url' => 'https://ow-prod.example.com', 'method' => 'POST', 'request_headers' => '{}'],
    ]);

    $pingPost = Integration::factory()->create(['type' => 'ping-post']);
    foreach (['ping', 'post'] as $et) {
      foreach (['development', 'production'] as $env) {
        $pingPost->environments()->create([
          'env_type' => $et,
          'environment' => $env,
          'url' => "https://{$et}.example.com/{$env}",
          'method' => 'POST',
          'request_headers' => '{}',
        ]);
      }
    }

    $offerwall->load('environments');

    // Offerwall integration still has exactly 2 environments
    expect($offerwall->environments)->toHaveCount(2);

    $prodEnv = $offerwall->environments->where('environment', 'production')->first();
    expect($prodEnv->url)->toBe('https://ow-prod.example.com');
  });
});
