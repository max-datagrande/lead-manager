<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('lead_dispatches', function (Blueprint $table): void {
      $table->id();
      $table->uuid('dispatch_uuid')->unique();
      $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
      $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
      $table->string('fingerprint')->index();
      $table->string('status', 20)->default('pending');
      $table->string('strategy_used', 20)->nullable();
      $table->foreignId('winner_integration_id')->nullable()->constrained('integrations')->nullOnDelete();
      $table->decimal('final_price', 10, 4)->nullable();
      $table->boolean('fallback_activated')->default(false);
      $table->unsignedInteger('total_duration_ms')->nullable();
      $table->text('error_message')->nullable();
      $table->timestamp('started_at')->nullable();
      $table->timestamp('completed_at')->nullable();
      $table->timestamps();

      $table->index(['workflow_id', 'status']);
      $table->index('lead_id');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('lead_dispatches');
  }
};
