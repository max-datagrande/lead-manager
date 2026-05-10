<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('buyer_eligibility_rules', function (Blueprint $table): void {
      $table->unsignedSmallInteger('group_index')->default(0)->after('value');
      $table->index(['integration_id', 'group_index']);
    });
  }

  public function down(): void
  {
    Schema::table('buyer_eligibility_rules', function (Blueprint $table): void {
      $table->dropIndex(['integration_id', 'group_index']);
      $table->dropColumn('group_index');
    });
  }
};
