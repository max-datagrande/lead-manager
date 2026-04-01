<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('workflow_buyers', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
      $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
      $table->unsignedSmallInteger('position')->default(0);
      $table->boolean('is_fallback')->default(false);
      $table->string('buyer_group', 20)->default('primary');
      $table->boolean('is_active')->default(true);
      $table->timestamps();

      $table->unique(['workflow_id', 'integration_id']);
      $table->index(['workflow_id', 'position']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('workflow_buyers');
  }
};
