<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public bool $withinTransaction = false;

  public function up(): void
  {
    if (!Schema::hasIndex('leads', 'leads_ip_address_index')) {
      if (DB::connection()->getDriverName() === 'pgsql') {
        DB::statement('CREATE INDEX CONCURRENTLY leads_ip_address_index ON leads (ip_address)');
      } else {
        DB::statement('CREATE INDEX leads_ip_address_index ON leads (ip_address)');
      }
    }
  }

  public function down(): void
  {
    if (Schema::hasIndex('leads', 'leads_ip_address_index')) {
      if (DB::connection()->getDriverName() === 'pgsql') {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS leads_ip_address_index');
      } else {
        DB::statement('DROP INDEX IF EXISTS leads_ip_address_index');
      }
    }
  }
};
