<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('buyer_eligibility_rules', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
      $table->string('field', 100);
      $table->string('operator', 20);
      $table->json('value');
      $table->unsignedSmallInteger('sort_order')->default(0);
      $table->timestamps();

      $table->index(['integration_id', 'sort_order']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('buyer_eligibility_rules');
  }
};
