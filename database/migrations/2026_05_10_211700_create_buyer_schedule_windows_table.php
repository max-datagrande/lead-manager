<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('buyer_schedule_windows', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('buyer_id')->constrained('buyers')->cascadeOnDelete();
      $table->json('days_of_week');
      $table->time('start_time');
      $table->time('end_time');
      $table->unsignedSmallInteger('sort_order')->default(0);
      $table->timestamps();

      $table->index(['buyer_id', 'sort_order']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('buyer_schedule_windows');
  }
};
