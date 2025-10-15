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
      $schemaManager = Schema::getConnection()->getDoctrineSchemaManager();
      $indexes = $schemaManager->listTableIndexes('postbacks');

      if (array_key_exists('postbacks_vendor_click_id_unique', $indexes)) {
        $table->dropUnique('postbacks_vendor_click_id_unique');
      }
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('postbacks', function (Blueprint $table) {
      $table->unique(['vendor', 'click_id']);
    });
  }
};
