<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // 1. Backup: solo en PostgreSQL (producción). Los datos se recuperan con:
    //    SELECT * FROM _integrations_parser_backup;
    if (DB::connection()->getDriverName() === 'pgsql') {
      DB::statement("
        CREATE TABLE _integrations_parser_backup AS
        SELECT id, name, response_parser_config
        FROM integrations
        WHERE response_parser_config IS NOT NULL
      ");
    }

    // 2. Backfill: copiar a cada fila de ambiente con env_type='offerwall'
    $integrations = DB::table('integrations')
      ->whereNotNull('response_parser_config')
      ->get(['id', 'response_parser_config']);

    foreach ($integrations as $integration) {
      DB::table('integration_environments')
        ->where('integration_id', $integration->id)
        ->where('env_type', 'offerwall')
        ->update(['response_config' => $integration->response_parser_config]);
    }

    // 3. Drop columna original
    Schema::table('integrations', function (Blueprint $table) {
      $table->dropColumn('response_parser_config');
    });
  }

  public function down(): void
  {
    // Re-agregar columna
    Schema::table('integrations', function (Blueprint $table) {
      $table->jsonb('response_parser_config')->nullable();
    });

    // Restaurar desde el ambiente offerwall de producción (fuente más confiable)
    $environments = DB::table('integration_environments')
      ->where('env_type', 'offerwall')
      ->where('environment', 'production')
      ->whereNotNull('response_config')
      ->get(['integration_id', 'response_config']);

    foreach ($environments as $env) {
      DB::table('integrations')
        ->where('id', $env->integration_id)
        ->update(['response_parser_config' => $env->response_config]);
    }

    if (DB::connection()->getDriverName() === 'pgsql') {
      DB::statement('DROP TABLE IF EXISTS _integrations_parser_backup');
    }
  }
};
