<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('postbacks', function (Blueprint $table) {
      $table->string('type', 20)->default('external')->after('name')->index();
    });

    if (DB::connection()->getDriverName() === 'pgsql') {
      DB::statement('ALTER TABLE postbacks ALTER COLUMN platform_id DROP NOT NULL');
    } else {
      Schema::table('postbacks', function (Blueprint $table) {
        $table->unsignedBigInteger('platform_id')->nullable()->change();
      });
    }
  }

  public function down(): void
  {
    if (DB::connection()->getDriverName() === 'pgsql') {
      DB::statement('ALTER TABLE postbacks ALTER COLUMN platform_id SET NOT NULL');
    } else {
      Schema::table('postbacks', function (Blueprint $table) {
        $table->unsignedBigInteger('platform_id')->nullable(false)->change();
      });
    }

    Schema::table('postbacks', function (Blueprint $table) {
      $table->dropColumn('type');
    });
  }
};
