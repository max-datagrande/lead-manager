<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('postback_executions', function (Blueprint $table) {
      $table->string('source', 30)->default('external_api')->after('postback_id')->index();
      $table->string('source_reference', 255)->nullable()->after('source');
    });
  }

  public function down(): void
  {
    Schema::table('postback_executions', function (Blueprint $table) {
      $table->dropColumn(['source', 'source_reference']);
    });
  }
};
