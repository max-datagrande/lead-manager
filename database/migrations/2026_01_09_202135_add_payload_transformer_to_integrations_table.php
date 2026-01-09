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
    Schema::table('integrations', function (Blueprint $table) {
      $table->longText('payload_transformer')->nullable();
      $table->boolean('use_custom_transformer')->default(false);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('integrations', function (Blueprint $table) {
      $table->dropColumn(['payload_transformer', 'use_custom_transformer']);
    });
  }
};
