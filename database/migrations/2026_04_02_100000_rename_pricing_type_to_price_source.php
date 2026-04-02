<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('buyer_configs', function (Blueprint $table): void {
      $table->renameColumn('pricing_type', 'price_source');
    });

    DB::table('buyer_configs')
      ->where('price_source', 'min_bid')
      ->update(['price_source' => 'response_bid']);

    Schema::table('buyer_configs', function (Blueprint $table): void {
      $table->boolean('sell_on_zero_price')->default(false)->after('min_bid');
    });
  }

  public function down(): void
  {
    Schema::table('buyer_configs', function (Blueprint $table): void {
      $table->dropColumn('sell_on_zero_price');
    });

    DB::table('buyer_configs')
      ->where('price_source', 'response_bid')
      ->update(['price_source' => 'min_bid']);

    Schema::table('buyer_configs', function (Blueprint $table): void {
      $table->renameColumn('price_source', 'pricing_type');
    });
  }
};
