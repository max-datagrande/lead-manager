<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    // 1. Backup (solo PostgreSQL — producción)
    if (DB::connection()->getDriverName() === 'pgsql') {
      DB::statement('CREATE TABLE _buyer_configs_backup AS SELECT * FROM buyer_configs');
    }

    // 2. Backfill IntegrationEnvironment desde BuyerConfig.
    //    - URL/method/headers/body: solo si el environment tiene url vacía (evita pisar datos ya configurados).
    //    - response_config: siempre si BuyerConfig lo tiene y el environment aún no.
    $configs = DB::table('buyer_configs')
      ->whereNotNull('integration_id')
      ->get([
        'integration_id',
        'ping_url',
        'ping_method',
        'ping_headers',
        'ping_body',
        'ping_response_config',
        'post_url',
        'post_method',
        'post_headers',
        'post_body',
        'post_response_config',
      ]);

    foreach ($configs as $cfg) {
      foreach (
        [
          ['ping', $cfg->ping_url, $cfg->ping_method, $cfg->ping_headers, $cfg->ping_body, $cfg->ping_response_config],
          ['post', $cfg->post_url, $cfg->post_method, $cfg->post_headers, $cfg->post_body, $cfg->post_response_config],
        ]
        as [$envType, $url, $method, $headers, $body, $responseConfig]
      ) {
        if ($url) {
          DB::table('integration_environments')
            ->where('integration_id', $cfg->integration_id)
            ->where('env_type', $envType)
            ->where('environment', 'production')
            ->where(fn($q) => $q->whereNull('url')->orWhere('url', ''))
            ->update([
              'url' => $url,
              'method' => $method ?? 'POST',
              'request_headers' => $headers ?? '{}',
              'request_body' => $body ?? '{}',
              'response_config' => $responseConfig,
            ]);
        }

        // Backfill response_config incluso si la URL ya estaba (response_config es nuevo)
        if ($responseConfig) {
          DB::table('integration_environments')
            ->where('integration_id', $cfg->integration_id)
            ->where('env_type', $envType)
            ->where('environment', 'production')
            ->whereNull('response_config')
            ->update(['response_config' => $responseConfig]);
        }
      }
    }

    // 3. Drop columnas de buyer_configs
    Schema::table('buyer_configs', function (Blueprint $table) {
      $table->dropColumn([
        'ping_url',
        'ping_method',
        'ping_headers',
        'ping_body',
        'ping_response_config',
        'post_url',
        'post_method',
        'post_headers',
        'post_body',
        'post_response_config',
      ]);
    });
  }

  public function down(): void
  {
    Schema::table('buyer_configs', function (Blueprint $table) {
      $table->string('ping_url')->nullable();
      $table->string('ping_method')->default('POST')->nullable();
      $table->json('ping_headers')->nullable();
      $table->text('ping_body')->nullable();
      $table->json('ping_response_config')->nullable();
      $table->string('post_url')->nullable();
      $table->string('post_method')->default('POST')->nullable();
      $table->json('post_headers')->nullable();
      $table->text('post_body')->nullable();
      $table->json('post_response_config')->nullable();
    });

    // Restaurar desde integration_environments producción
    $envs = DB::table('integration_environments')
      ->whereIn('env_type', ['ping', 'post'])
      ->where('environment', 'production')
      ->get(['integration_id', 'env_type', 'url', 'method', 'request_headers', 'request_body', 'response_config']);

    foreach ($envs as $env) {
      $prefix = $env->env_type;
      DB::table('buyer_configs')
        ->where('integration_id', $env->integration_id)
        ->update([
          "{$prefix}_url" => $env->url,
          "{$prefix}_method" => $env->method,
          "{$prefix}_headers" => $env->request_headers,
          "{$prefix}_body" => $env->request_body,
          "{$prefix}_response_config" => $env->response_config,
        ]);
    }

    if (DB::connection()->getDriverName() === 'pgsql') {
      DB::statement('DROP TABLE IF EXISTS _buyer_configs_backup');
    }
  }
};
