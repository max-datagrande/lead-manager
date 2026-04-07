<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('lead_dispatches', function (Blueprint $table) {
      $table->unsignedSmallInteger('attempt')->default(1)->after('strategy_used');
      $table->foreignId('parent_dispatch_id')->nullable()->after('attempt')->constrained('lead_dispatches')->nullOnDelete();
    });
  }

  public function down(): void
  {
    Schema::table('lead_dispatches', function (Blueprint $table) {
      $table->dropConstrainedForeignId('parent_dispatch_id');
      $table->dropColumn('attempt');
    });
  }
};
