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
    Schema::table('integration_call_logs', function (Blueprint $table) {
      $table->json('original_field_values')->nullable();
      $table->json('mapped_field_values')->nullable();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('integration_call_logs', function (Blueprint $table) {
      $table->dropColumn(['original_field_values', 'mapped_field_values']);
    });
  }
};
