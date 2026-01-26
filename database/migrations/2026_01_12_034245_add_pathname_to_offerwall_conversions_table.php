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
    Schema::table('offerwall_conversions', function (Blueprint $table) {
      // Se usa string para permitir cualquier ruta, incluyendo '/'
      $table->string('pathname')->nullable()->after('offer_data')->index();
    });

  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('offerwall_conversions', function (Blueprint $table) {
      $table->dropColumn('pathname');
    });
  }
};
