<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('ping_response_configs', function (Blueprint $table) {
      $table->json('error_excludes')->nullable()->after('error_reason_path');
    });

    Schema::table('post_response_configs', function (Blueprint $table) {
      $table->json('error_excludes')->nullable()->after('error_reason_path');
    });
  }

  public function down(): void
  {
    Schema::table('ping_response_configs', function (Blueprint $table) {
      $table->dropColumn('error_excludes');
    });

    Schema::table('post_response_configs', function (Blueprint $table) {
      $table->dropColumn('error_excludes');
    });
  }
};
