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
    Schema::create('postback_workflow', function (Blueprint $table) {
      $table->id();
      $table->foreignId('postback_id')->constrained('postbacks')->cascadeOnDelete();
      $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
      $table->timestamps();

      $table->unique(['postback_id', 'workflow_id']);
      $table->index('workflow_id');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('postback_workflow');
  }
};
