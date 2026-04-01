<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('ping_results', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('lead_dispatch_id')->constrained('lead_dispatches')->cascadeOnDelete();
      $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
      $table->string('idempotency_key', 64)->unique();
      $table->string('status', 20);
      $table->decimal('bid_price', 10, 4)->nullable();
      $table->unsignedSmallInteger('http_status_code')->nullable();
      $table->text('request_url')->nullable();
      $table->json('request_payload')->nullable();
      $table->json('request_headers')->nullable();
      $table->json('response_body')->nullable();
      $table->unsignedInteger('duration_ms')->nullable();
      $table->text('skip_reason')->nullable();
      $table->unsignedTinyInteger('attempt_count')->default(1);
      $table->timestamps();

      $table->index('lead_dispatch_id');
      $table->index(['integration_id', 'created_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('ping_results');
  }
};
