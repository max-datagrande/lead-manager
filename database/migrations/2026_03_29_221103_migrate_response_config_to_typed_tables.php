<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  /**
   * Migrate existing response_config JSON data from integration_environments
   * into the new typed response config tables.
   */
  public function up(): void
  {
    $environments = DB::table('integration_environments')
      ->whereNotNull('response_config')
      ->get(['id', 'env_type', 'response_config']);

    $defaultMapping = [
      'title' => null,
      'description' => null,
      'logo_url' => null,
      'click_url' => null,
      'impression_url' => null,
      'cpc' => null,
      'display_name' => null,
      'company' => null,
    ];

    $defaultFallbacks = [
      'title' => null,
      'description' => null,
    ];

    foreach ($environments as $env) {
      $config = json_decode($env->response_config, true);

      if (empty($config) || !is_array($config)) {
        continue;
      }

      $now = now();

      match ($env->env_type) {
        'offerwall' => DB::table('offerwall_response_configs')->insertOrIgnore([
          'integration_environment_id' => $env->id,
          'offer_list_path' => $config['offer_list_path'] ?? null,
          'mapping' => json_encode(array_merge($defaultMapping, $config['mapping'] ?? [])),
          'fallbacks' => json_encode(array_merge($defaultFallbacks, $config['fallbacks'] ?? [])),
          'created_at' => $now,
          'updated_at' => $now,
        ]),

        'ping' => DB::table('ping_response_configs')->insertOrIgnore([
          'integration_environment_id' => $env->id,
          'bid_price_path' => $config['bid_price_path'] ?? null,
          'accepted_path' => $config['accepted_path'] ?? null,
          'accepted_value' => $config['accepted_value'] ?? null,
          'lead_id_path' => $config['lead_id_path'] ?? null,
          'created_at' => $now,
          'updated_at' => $now,
        ]),

        'post' => DB::table('post_response_configs')->insertOrIgnore([
          'integration_environment_id' => $env->id,
          'accepted_path' => $config['accepted_path'] ?? null,
          'accepted_value' => $config['accepted_value'] ?? null,
          'rejected_path' => $config['rejected_path'] ?? null,
          'created_at' => $now,
          'updated_at' => $now,
        ]),

        default => null,
      };
    }
  }

  /**
   * Reverse: copy data back from typed tables to the JSON column.
   */
  public function down(): void
  {
    // Offerwall
    $offerwallConfigs = DB::table('offerwall_response_configs')->get();
    foreach ($offerwallConfigs as $config) {
      DB::table('integration_environments')
        ->where('id', $config->integration_environment_id)
        ->update([
          'response_config' => json_encode([
            'offer_list_path' => $config->offer_list_path,
            'mapping' => json_decode($config->mapping, true),
            'fallbacks' => json_decode($config->fallbacks, true),
          ]),
        ]);
    }

    // Ping
    $pingConfigs = DB::table('ping_response_configs')->get();
    foreach ($pingConfigs as $config) {
      DB::table('integration_environments')
        ->where('id', $config->integration_environment_id)
        ->update([
          'response_config' => json_encode([
            'bid_price_path' => $config->bid_price_path,
            'accepted_path' => $config->accepted_path,
            'accepted_value' => $config->accepted_value,
            'lead_id_path' => $config->lead_id_path,
          ]),
        ]);
    }

    // Post
    $postConfigs = DB::table('post_response_configs')->get();
    foreach ($postConfigs as $config) {
      DB::table('integration_environments')
        ->where('id', $config->integration_environment_id)
        ->update([
          'response_config' => json_encode([
            'accepted_path' => $config->accepted_path,
            'accepted_value' => $config->accepted_value,
            'rejected_path' => $config->rejected_path,
          ]),
        ]);
    }
  }
};
