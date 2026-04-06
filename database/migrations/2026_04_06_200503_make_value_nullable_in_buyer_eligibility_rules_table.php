<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('buyer_eligibility_rules', function (Blueprint $table): void {
      $table->json('value')->nullable()->change();
    });
  }

  public function down(): void
  {
    Schema::table('buyer_eligibility_rules', function (Blueprint $table): void {
      $table->json('value')->nullable(false)->change();
    });
  }
};
