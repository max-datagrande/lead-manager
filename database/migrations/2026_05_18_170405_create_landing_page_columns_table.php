<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('landing_page_columns', function (Blueprint $table) {
      $table->id();
      $table->foreignId('landing_page_id')->constrained()->cascadeOnDelete();
      $table->string('source');
      $table->string('reference');
      $table->timestamps();

      $table->unique(['landing_page_id', 'source', 'reference']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('landing_page_columns');
  }
};
