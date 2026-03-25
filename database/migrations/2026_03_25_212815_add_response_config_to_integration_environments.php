<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('integration_environments', function (Blueprint $table) {
      $table->jsonb('response_config')->nullable()->after('request_body');
    });
  }

  public function down(): void
  {
    Schema::table('integration_environments', function (Blueprint $table) {
      $table->dropColumn('response_config');
    });
  }
};
