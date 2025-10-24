<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::table('traffic_logs', function (Blueprint $table) {
      // Renombrar columnas existentes
      $table->renameColumn('traffic_source', 'utm_source');
      $table->renameColumn('traffic_medium', 'utm_medium');
      $table->renameColumn('campaign_id', 'utm_campaign_id');

      // Agregar nuevas columnas UTM
      $table->string('utm_campaign_name')->nullable()->after('utm_campaign_id');
      $table->string('utm_term')->nullable()->after('utm_campaign_name');
      $table->string('utm_content')->nullable()->after('utm_term');

      // Agregar columnas de plataforma y origen
      $table->string('platform')->nullable()->after('utm_content');
      $table->string('channel')->nullable()->after('platform');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('traffic_logs', function (Blueprint $table) {
      // Eliminar columnas agregadas
      $table->dropColumn(['utm_campaign_name', 'utm_term', 'utm_content', 'platform', 'channel']);

      // Revertir nombres de columnas
      $table->renameColumn('utm_source', 'traffic_source');
      $table->renameColumn('utm_medium', 'traffic_medium');
      $table->renameColumn('utm_campaign_id', 'campaign_id');
    });
  }
};
