<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  public function up(): void
  {
    if (DB::connection()->getDriverName() === 'pgsql') {
      DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS leads_fingerprint_unique ON leads (fingerprint)');
    } else {
      // SQLite for tests
      DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS leads_fingerprint_unique ON leads (fingerprint)');
    }
  }

  public function down(): void
  {
    DB::statement('DROP INDEX IF EXISTS leads_fingerprint_unique');
  }
};
