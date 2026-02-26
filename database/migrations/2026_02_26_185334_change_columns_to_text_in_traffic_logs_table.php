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
      $table->text('referrer')->nullable()->change();
      $table->text('user_agent')->nullable()->change();
      $table->text('host')->nullable()->change();
      $table->text('path_visited')->nullable()->change();
      $table->text('utm_source')->nullable()->change();
      $table->text('utm_medium')->nullable()->change();
      $table->text('utm_campaign_id')->nullable()->change();
      $table->text('utm_campaign_name')->nullable()->change();
      $table->text('utm_term')->nullable()->change();
      $table->text('utm_content')->nullable()->change();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('traffic_logs', function (Blueprint $table) {
      $table->text('referrer')->nullable()->change();
      $table->string('user_agent', 255)->nullable()->change();
      $table->string('host', 255)->nullable()->change();
      $table->string('path_visited', 255)->nullable()->change();
      $table->string('utm_source', 255)->nullable()->change();
      $table->string('utm_medium', 255)->nullable()->change();
      $table->string('utm_campaign_id', 255)->nullable()->change();
      $table->string('utm_campaign_name', 255)->nullable()->change();
      $table->string('utm_term', 255)->nullable()->change();
      $table->string('utm_content', 255)->nullable()->change();
    });
  }
};
