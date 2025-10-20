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
    Schema::table('postbacks', function (Blueprint $table) {
      $indexes = Schema::getIndexes('postbacks');

      foreach ($indexes as $index) {
        if ($index['name'] === 'postbacks_vendor_click_id_unique') {
          $table->dropUnique('postbacks_vendor_click_id_unique');
          break;
        }
      }
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('postbacks', function (Blueprint $table) {
    });
  }
};
