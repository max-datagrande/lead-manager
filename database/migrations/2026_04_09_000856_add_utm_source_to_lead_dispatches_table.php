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
    Schema::table('lead_dispatches', function (Blueprint $table) {
      $table->string('utm_source', 100)->nullable()->after('lead_snapshot');
      $table->index('utm_source');
    });
  }

  public function down(): void
  {
    Schema::table('lead_dispatches', function (Blueprint $table) {
      $table->dropIndex(['utm_source']);
      $table->dropColumn('utm_source');
    });
  }
};
