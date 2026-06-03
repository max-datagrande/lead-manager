<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   *
   * Ensancha a text las columnas de la familia atribucion que eran varchar(255).
   * Motivo: los click ids de TikTok (ttclid / ext_click_id) superan 255 chars y
   * rompian la ingestion con SQLSTATE[22001] (value too long for type character
   * varying(255)), perdiendo la visita entera. text no tiene penalidad vs varchar
   * en Postgres y evita repetir la falla con otras redes.
   */
  public function up(): void
  {
    Schema::table('traffic_logs', function (Blueprint $table) {
      $table->text('click_id')->nullable()->change();
      $table->text('campaign_code')->nullable()->change();
      $table->text('platform')->nullable()->change();
      $table->text('channel')->nullable()->change();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('traffic_logs', function (Blueprint $table) {
      $table->string('click_id', 255)->nullable()->change();
      $table->string('campaign_code', 255)->nullable()->change();
      $table->string('platform', 255)->nullable()->change();
      $table->string('channel', 255)->nullable()->change();
    });
  }
};
