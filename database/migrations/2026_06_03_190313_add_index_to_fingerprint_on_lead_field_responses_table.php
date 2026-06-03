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
   * The visitors listing flags each row with `has_field_data` via a correlated
   * EXISTS subquery on `lead_field_responses.fingerprint`. That column had no
   * index, forcing a sequential scan per visible row. On PostgreSQL we build
   * CONCURRENTLY to avoid blocking writes; SQLite (tests) is tiny and has no
   * CONCURRENTLY, so the regular Blueprint index is fine.
   */
  public function up(): void
  {
    if (DB::connection()->getDriverName() === 'pgsql') {
      DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS lead_field_responses_fingerprint_index ON lead_field_responses (fingerprint)');

      return;
    }

    Schema::table('lead_field_responses', function (Blueprint $table) {
      $table->index('fingerprint');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    if (DB::connection()->getDriverName() === 'pgsql') {
      DB::statement('DROP INDEX CONCURRENTLY IF EXISTS lead_field_responses_fingerprint_index');

      return;
    }

    Schema::table('lead_field_responses', function (Blueprint $table) {
      $table->dropIndex(['fingerprint']);
    });
  }
};
