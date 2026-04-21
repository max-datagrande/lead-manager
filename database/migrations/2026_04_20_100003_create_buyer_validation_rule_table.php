<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('buyer_validation_rule', function (Blueprint $table) {
      $table->id();
      $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
      $table->foreignId('validation_rule_id')->constrained('lead_quality_validation_rules')->cascadeOnDelete();
      $table->boolean('is_enabled')->default(true);
      $table->timestamps();

      $table->unique(['integration_id', 'validation_rule_id']);
      $table->index('validation_rule_id');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('buyer_validation_rule');
  }
};
