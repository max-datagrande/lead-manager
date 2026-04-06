<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('postback_dispatch_logs', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('execution_id')->constrained('postback_executions')->cascadeOnDelete();
      $table->unsignedTinyInteger('attempt_number');
      $table->text('request_url');
      $table->string('request_method', 10)->default('GET');
      $table->json('request_headers')->nullable();
      $table->unsignedSmallInteger('response_status_code')->nullable();
      $table->text('response_body')->nullable();
      $table->json('response_headers')->nullable();
      $table->unsignedInteger('response_time_ms')->nullable();
      $table->text('error_message')->nullable();
      $table->timestamps();

      $table->index(['execution_id', 'attempt_number']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('postback_dispatch_logs');
  }
};
