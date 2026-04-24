<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('integration_notes', function (Blueprint $table) {
      $table->id();
      $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
      $table->text('content')->nullable();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('integration_notes');
  }
};
