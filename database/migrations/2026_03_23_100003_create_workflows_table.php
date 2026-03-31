<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('workflows', function (Blueprint $table): void {
      $table->id();
      $table->string('name');
      $table->string('execution_mode', 10)->default('sync');
      $table->string('strategy', 20)->default('best_bid');
      $table->unsignedInteger('global_timeout_ms')->default(3000);
      $table->boolean('is_active')->default(true);
      $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
      $table->boolean('cascade_on_post_rejection')->default(true);
      $table->unsignedTinyInteger('cascade_max_retries')->default(3);
      $table->boolean('advance_on_rejection')->default(true);
      $table->boolean('advance_on_timeout')->default(true);
      $table->boolean('advance_on_error')->default(false);
      $table->timestamps();

      $table->index(['is_active', 'created_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('workflows');
  }
};
