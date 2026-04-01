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
    Schema::create('integration_field_mappings', function (Blueprint $table) {
      $table->id();
      $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
      $table->foreignId('field_id')->constrained('fields')->restrictOnDelete();
      $table->string('data_type')->default('string')->comment('Possible values: string | integer | boolean | float');
      $table->string('default_value')->nullable();
      $table->json('value_mapping')->nullable();
      $table->unique(['integration_id', 'field_id']);
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('integration_field_mappings');
  }
};
