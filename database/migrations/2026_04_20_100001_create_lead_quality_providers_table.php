<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('lead_quality_providers', function (Blueprint $table) {
      $table->id();
      $table->string('name', 120)->unique();
      $table->string('type', 40);
      $table->string('status', 20)->default('inactive');
      $table->boolean('is_enabled')->default(false);
      $table->string('environment', 20)->default('production');
      $table->text('credentials')->nullable();
      $table->json('settings')->nullable();
      $table->text('notes')->nullable();
      $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->foreignId('updated_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();

      $table->index('type');
      $table->index('status');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('lead_quality_providers');
  }
};
