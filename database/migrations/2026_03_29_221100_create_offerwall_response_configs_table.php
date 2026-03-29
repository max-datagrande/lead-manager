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
    Schema::create('offerwall_response_configs', function (Blueprint $table) {
      $table->id();
      $table->foreignId('integration_environment_id')
        ->unique()
        ->constrained('integration_environments')
        ->cascadeOnDelete();
      $table->string('offer_list_path')->nullable();
      $table->jsonb('mapping')->nullable();
      $table->jsonb('fallbacks')->nullable();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('offerwall_response_configs');
  }
};
