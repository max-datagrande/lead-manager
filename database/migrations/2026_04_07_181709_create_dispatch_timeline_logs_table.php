<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('dispatch_timeline_logs', function (Blueprint $table): void {
      $table->id();
      $table->string('fingerprint', 64);
      $table->foreignId('lead_dispatch_id')->constrained('lead_dispatches')->cascadeOnDelete();
      $table->string('event', 40);
      $table->string('message');
      $table->json('context')->nullable();
      $table->timestamp('logged_at', 6);
      $table->timestamps();

      $table->index(['fingerprint', 'logged_at']);
      $table->index(['lead_dispatch_id', 'logged_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('dispatch_timeline_logs');
  }
};
