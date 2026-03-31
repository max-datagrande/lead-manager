<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('buyer_cap_rules', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
      $table->string('period', 10);
      $table->unsignedInteger('max_leads')->nullable();
      $table->decimal('max_revenue', 12, 2)->nullable();
      $table->timestamps();

      $table->index('integration_id');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('buyer_cap_rules');
  }
};
