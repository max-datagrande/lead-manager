<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('post_response_configs', function (Blueprint $table) {
      $table->id();
      $table->foreignId('integration_environment_id')->unique()->constrained('integration_environments')->cascadeOnDelete();
      $table->string('accepted_path')->nullable();
      $table->string('accepted_value')->nullable();
      $table->string('rejected_path')->nullable();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('post_response_configs');
  }
};
