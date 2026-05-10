<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('buyer_configs', function (Blueprint $table): void {
      $table->string('schedule_timezone')->nullable()->after('postback_pending_days');
    });
  }

  public function down(): void
  {
    Schema::table('buyer_configs', function (Blueprint $table): void {
      $table->dropColumn('schedule_timezone');
    });
  }
};
