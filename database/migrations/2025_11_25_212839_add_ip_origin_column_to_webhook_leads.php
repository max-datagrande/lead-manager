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
    Schema::table('webhook_leads', function (Blueprint $table) {
      $table->json('headers')->after('email')->nullable();
      $table->string('ip_origin')->after('headers')->nullable();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('webhook_leads', function (Blueprint $table) {
      $table->dropColumn('headers');
      $table->dropColumn('ip_origin');
    });
  }
};
