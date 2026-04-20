<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('lead_quality_validation_logs', function (Blueprint $table) {
      $table->id();
      $table->foreignId('validation_rule_id')->nullable()->constrained('lead_quality_validation_rules')->nullOnDelete();
      $table->foreignId('integration_id')->nullable()->constrained('integrations')->nullOnDelete();
      $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
      $table->foreignId('provider_id')->nullable()->constrained('lead_quality_providers')->nullOnDelete();
      $table->foreignId('lead_dispatch_id')->nullable()->constrained('lead_dispatches')->nullOnDelete();
      $table->string('fingerprint', 64)->nullable();
      $table->string('status', 20)->default('pending');
      $table->unsignedTinyInteger('attempts_count')->default(0);
      $table->string('result', 30)->nullable();
      $table->json('context')->nullable();
      $table->string('message', 500)->nullable();
      $table->string('challenge_reference', 120)->nullable();
      $table->timestamp('started_at')->nullable();
      $table->timestamp('resolved_at')->nullable();
      $table->timestamp('expires_at')->nullable();
      $table->timestamps();

      $table->index('fingerprint');
      $table->index(['fingerprint', 'integration_id']);
      $table->index(['validation_rule_id', 'status']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('lead_quality_validation_logs');
  }
};
