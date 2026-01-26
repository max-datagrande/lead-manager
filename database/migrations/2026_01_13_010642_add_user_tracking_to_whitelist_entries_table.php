<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::table('whitelist_entries', function (Blueprint $table) {
      $table->foreignId('user_id')->default(1)->constrained('users')->after('id');
      $table->foreignId('updated_user_id')->nullable()->constrained('users')->after('user_id');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('whitelist_entries', function (Blueprint $table) {
      $table->dropForeign(['user_id']);
      $table->dropForeign(['updated_user_id']);
      $table->dropColumn(['user_id', 'updated_user_id']);
    });
  }
};
