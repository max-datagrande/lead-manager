<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('dispatch_buyer_events', function (Blueprint $table) {
      $table->id();
      $table->foreignId('lead_dispatch_id')->constrained('lead_dispatches')->cascadeOnDelete();
      $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
      $table->string('event', 30);
      $table->string('reason', 30);
      $table->text('detail')->nullable();
      $table->timestamp('created_at')->nullable();

      $table->index(['integration_id', 'created_at']);
      $table->index('lead_dispatch_id');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('dispatch_buyer_events');
  }
};
