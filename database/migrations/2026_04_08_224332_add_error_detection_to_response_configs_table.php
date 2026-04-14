<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('ping_response_configs', function (Blueprint $table) {
      $table->string('error_path')->nullable();
      $table->string('error_value')->nullable();
      $table->string('error_reason_path')->nullable();
    });

    Schema::table('post_response_configs', function (Blueprint $table) {
      $table->string('error_path')->nullable();
      $table->string('error_value')->nullable();
      $table->string('error_reason_path')->nullable();
    });
  }

  public function down(): void
  {
    Schema::table('ping_response_configs', function (Blueprint $table) {
      $table->dropColumn(['error_path', 'error_value', 'error_reason_path']);
    });

    Schema::table('post_response_configs', function (Blueprint $table) {
      $table->dropColumn(['error_path', 'error_value', 'error_reason_path']);
    });
  }
};
