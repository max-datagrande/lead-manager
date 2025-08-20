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
        Schema::table('leads', function (Blueprint $table) {
          $table->dropColumn('state');
          $table->dropColumn('city');
          $table->dropColumn('postal_code');
          $table->dropColumn('country_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
          $table->string('country_code', 2)->nullable();
          $table->string('postal_code', 100)->nullable();
          $table->string('state', 100)->nullable();
          $table->string('city', 100)->nullable();
        });
    }
};
