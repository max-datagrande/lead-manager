<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (!Schema::hasColumn('leads', 'ip_address')) {
      Schema::table('leads', function (Blueprint $table) {
        $table->string('ip_address', 45)->nullable()->after('fingerprint');
        $table->index('ip_address');
      });
    }
  }

  public function down(): void
  {
    if (Schema::hasColumn('leads', 'ip_address')) {
      Schema::table('leads', function (Blueprint $table) {
        $table->dropIndex(['ip_address']);
        $table->dropColumn('ip_address');
      });
    }
  }
};
