<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('cron_logs', function (Blueprint $table) {
      $table->id();
      $table->string('command');              // nombre del comando artisan
      $table->enum('status', ['success', 'error'])->default('success');
      $table->text('output')->nullable();     // salida completa
      $table->text('exception')->nullable();  // mensaje de error si hay
      $table->float('duration', 8, 2)->nullable(); // en segundos
      $table->timestamp('executed_at')->useCurrent();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('cron_logs');
  }
};
