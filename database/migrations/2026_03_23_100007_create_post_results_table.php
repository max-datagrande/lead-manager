<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('post_results', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('lead_dispatch_id')->constrained('lead_dispatches')->cascadeOnDelete();
      $table->foreignId('ping_result_id')->nullable()->constrained('ping_results')->nullOnDelete();
      $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
      $table->string('status', 20);
      $table->decimal('price_offered', 10, 4)->nullable();
      $table->decimal('price_final', 10, 4)->nullable();
      $table->unsignedSmallInteger('http_status_code')->nullable();
      $table->text('request_url')->nullable();
      $table->json('request_payload')->nullable();
      $table->json('request_headers')->nullable();
      $table->json('response_body')->nullable();
      $table->unsignedInteger('duration_ms')->nullable();
      $table->text('rejection_reason')->nullable();
      $table->unsignedTinyInteger('attempt_count')->default(1);
      $table->timestamp('postback_received_at')->nullable();
      $table->timestamp('postback_expires_at')->nullable();
      $table->timestamps();

      $table->index('lead_dispatch_id');
      $table->index(['status', 'postback_expires_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('post_results');
  }
};
