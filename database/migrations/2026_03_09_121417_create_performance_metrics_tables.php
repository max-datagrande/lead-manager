<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('performance_metrics', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->string('fingerprint', 255)->nullable();
      $table->string('host', 255);
      $table->integer('load_time_ms');
      $table->string('device_type', 50)->nullable();
      $table->date('recorded_at');
      $table->timestamp('created_at')->useCurrent();

      $table->index(['host', 'recorded_at']);
      $table->index('recorded_at');
    });

    Schema::create('performance_metrics_daily', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->string('host', 255);
      $table->date('recorded_date');
      $table->integer('request_count')->default(0);
      $table->bigInteger('total_ms')->default(0);
      $table->decimal('avg_ms', 10, 2)->default(0);
      $table->integer('min_ms')->default(0);
      $table->integer('max_ms')->default(0);
      $table->timestamps();

      $table->unique(['host', 'recorded_date']);
      $table->index('recorded_date');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('performance_metrics_daily');
    Schema::dropIfExists('performance_metrics');
  }
};
