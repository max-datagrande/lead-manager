<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\TrafficLog;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    // Primero obtener los fingerprints de traffic logs sin origen
    $fingerprints = TrafficLog::whereNull('host')
      ->orWhere('host', '')
      ->pluck('fingerprint');

    // Eliminar leads asociados a esos fingerprints
    if ($fingerprints->isNotEmpty()) {
      Lead::whereIn('fingerprint', $fingerprints)->delete();
    }

    // Eliminar traffic logs sin origen (null o vacío)
    TrafficLog::whereNull('host')
      ->orWhere('host', '')
      ->delete();
  }


  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    //
  }
};
