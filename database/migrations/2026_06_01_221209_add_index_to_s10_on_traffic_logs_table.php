<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Build/drop the index OUTSIDE a transaction so PostgreSQL can use
   * CREATE INDEX CONCURRENTLY (which is illegal inside a transaction block).
   */
  public $withinTransaction = false;

  /**
   * Run the migrations.
   *
   * `traffic_logs` is a high-write ingestion table with millions of rows, so a
   * plain blocking CREATE INDEX would lock out writes for the whole build. On
   * PostgreSQL we build CONCURRENTLY to avoid blocking ingestion; SQLite (tests)
   * has no CONCURRENTLY and is tiny, so the regular Blueprint index is fine.
   */
  public function up(): void
  {
    if (DB::connection()->getDriverName() === 'pgsql') {
      DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS traffic_logs_s10_index ON traffic_logs (s10)');

      return;
    }

    Schema::table('traffic_logs', function (Blueprint $table) {
      $table->index('s10');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    if (DB::connection()->getDriverName() === 'pgsql') {
      DB::statement('DROP INDEX CONCURRENTLY IF EXISTS traffic_logs_s10_index');

      return;
    }

    Schema::table('traffic_logs', function (Blueprint $table) {
      $table->dropIndex(['s10']);
    });
  }
};
