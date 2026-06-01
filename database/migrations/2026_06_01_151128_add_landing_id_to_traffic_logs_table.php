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
    Schema::table('traffic_logs', function (Blueprint $table) {
      $table->foreignId('landing_id')->nullable()->after('host')->constrained('landing_pages')->nullOnDelete();
      $table->foreignId('landing_page_version_id')->nullable()->after('landing_id')->constrained('landing_page_versions')->nullOnDelete();

      $table->index('landing_id');
      $table->index('landing_page_version_id');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('traffic_logs', function (Blueprint $table) {
      $table->dropConstrainedForeignId('landing_id');
      $table->dropConstrainedForeignId('landing_page_version_id');
    });
  }
};
