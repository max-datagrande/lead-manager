<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('platforms', function (Blueprint $table): void {
      $table->renameColumn('tokens', 'token_mappings');
    });
  }

  public function down(): void
  {
    Schema::table('platforms', function (Blueprint $table): void {
      $table->renameColumn('token_mappings', 'tokens');
    });
  }
};
