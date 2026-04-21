<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('lead_quality_validation_rules', function (Blueprint $table) {
      $table->id();
      $table->string('name', 140);
      $table->string('slug', 160)->unique();
      $table->string('validation_type', 40);
      $table->foreignId('provider_id')->constrained('lead_quality_providers')->restrictOnDelete();
      $table->string('status', 20)->default('draft');
      $table->boolean('is_enabled')->default(false);
      $table->text('description')->nullable();
      $table->json('settings')->nullable();
      $table->unsignedSmallInteger('priority')->default(100);
      $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->foreignId('updated_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();

      $table->index('validation_type');
      $table->index('status');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('lead_quality_validation_rules');
  }
};
