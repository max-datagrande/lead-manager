<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('buyer_configs', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('integration_id')->unique()->constrained('integrations')->cascadeOnDelete();
      $table->foreignId('ping_environment_id')->nullable()->constrained('integration_environments')->nullOnDelete();
      $table->unsignedInteger('ping_timeout_ms')->default(3000);
      $table->foreignId('post_environment_id')->nullable()->constrained('integration_environments')->nullOnDelete();
      $table->unsignedInteger('post_timeout_ms')->default(5000);
      $table->json('ping_response_config')->nullable();
      $table->json('post_response_config')->nullable();
      $table->string('pricing_type', 20)->default('fixed');
      $table->decimal('fixed_price', 10, 4)->nullable();
      $table->decimal('min_bid', 10, 4)->nullable();
      $table->json('conditional_pricing_rules')->nullable();
      $table->unsignedTinyInteger('postback_pending_days')->default(15);
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('buyer_configs');
  }
};
