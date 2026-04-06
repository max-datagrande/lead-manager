<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('buyer_configs', function (Blueprint $table) {
      $table->unsignedInteger('ping_timeout_ms')->nullable()->default(3000)->change();
    });
  }

  public function down(): void
  {
    Schema::table('buyer_configs', function (Blueprint $table) {
      $table->unsignedInteger('ping_timeout_ms')->nullable(false)->default(3000)->change();
    });
  }
};
