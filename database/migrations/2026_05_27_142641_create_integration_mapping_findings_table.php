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
    Schema::create('integration_mapping_findings', function (Blueprint $table) {
      $table->id();
      $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
      $table->foreignId('field_id')->constrained('fields')->cascadeOnDelete();
      $table->string('status')->default('open')->comment('Possible values: open | resolved | ignored');
      $table->timestamp('first_detected_at')->nullable();
      $table->timestamp('last_seen_at')->nullable();
      $table->timestamp('resolved_at')->nullable();
      $table->timestamps();
      $table->unique(['integration_id', 'field_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('integration_mapping_findings');
  }
};
