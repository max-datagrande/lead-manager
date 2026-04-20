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
    Schema::create('buyer_config_postback', function (Blueprint $table) {
      $table->id();
      $table->foreignId('buyer_config_id')->constrained('buyer_configs')->cascadeOnDelete();
      $table->foreignId('postback_id')->constrained('postbacks')->cascadeOnDelete();
      $table->string('identifier_token');
      $table->string('price_token');
      $table->timestamps();

      $table->unique(['buyer_config_id', 'postback_id']);
      $table->index('postback_id');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('buyer_config_postback');
  }
};
