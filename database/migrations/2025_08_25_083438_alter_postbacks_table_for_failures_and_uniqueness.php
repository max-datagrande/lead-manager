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
    Schema::table('postbacks', function (Blueprint $table) {
      // 1. Añadir columna para guardar el motivo del fallo.
      $table->text('failure_reason')->nullable()->after('status');

      // 2. Añadir índice único para evitar postbacks duplicados por vendor y txid.
      // txid puede ser nulo, por lo que la restricción única podría no aplicarse
      // correctamente en todos los motores de BD. Una alternativa es manejarlo a nivel de aplicación.
      // Sin embargo, para los casos donde txid está presente, esto es útil.
      $table->unique(['vendor', 'txid']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('postbacks', function (Blueprint $table) {
      $table->dropUnique(['vendor', 'txid']);
      $table->dropColumn('failure_reason');
    });
  }
};
