<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Remove the response_config JSON column now that data lives in typed tables.
   */
  public function up(): void
  {
    Schema::table('integration_environments', function (Blueprint $table) {
      $table->dropColumn('response_config');
    });
  }

  /**
   * Restore the response_config column (data must be re-populated separately).
   */
  public function down(): void
  {
    Schema::table('integration_environments', function (Blueprint $table) {
      $table->jsonb('response_config')->nullable()->after('request_body');
    });
  }
};
