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
    Schema::table('post_response_configs', function (Blueprint $table) {
      $table->string('bid_price_path')->nullable()->after('rejected_path');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('post_response_configs', function (Blueprint $table) {
      $table->dropColumn('bid_price_path');
    });
  }
};
