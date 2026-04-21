<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('external_service_requests', function (Blueprint $table) {
      $table->id();
      $table->string('loggable_type', 120)->nullable();
      $table->unsignedBigInteger('loggable_id')->nullable();
      $table->string('module', 40);
      $table->string('service_name', 60)->nullable();
      $table->unsignedBigInteger('service_id')->nullable();
      $table->string('operation', 60)->nullable();
      $table->string('request_method', 10);
      $table->string('request_url', 2048);
      $table->json('request_headers')->nullable();
      $table->json('request_body')->nullable();
      $table->unsignedSmallInteger('response_status_code')->nullable();
      $table->json('response_headers')->nullable();
      $table->json('response_body')->nullable();
      $table->string('status', 20);
      $table->text('error_message')->nullable();
      $table->unsignedInteger('duration_ms')->nullable();
      $table->timestamp('requested_at')->nullable();
      $table->timestamp('responded_at')->nullable();
      $table->timestamps();

      $table->index('module');
      $table->index(['loggable_type', 'loggable_id']);
      $table->index(['module', 'operation']);
      $table->index(['service_name', 'status']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('external_service_requests');
  }
};
