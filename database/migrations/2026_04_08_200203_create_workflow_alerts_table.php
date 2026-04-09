<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('workflow_alerts', function (Blueprint $table) {
      $table->id();
      $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
      $table->foreignId('alert_channel_id')->constrained()->cascadeOnDelete();
      $table->boolean('is_active')->default(true);
      $table->timestamps();

      $table->unique(['workflow_id', 'alert_channel_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('workflow_alerts');
  }
};
