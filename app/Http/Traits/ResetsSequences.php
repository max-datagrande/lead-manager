<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\DB;

trait ResetsSequences
{
  /**
   * Reset the auto-increment sequence for a table to MAX(id) + 1.
   * Only applies to PostgreSQL; silently skips other drivers.
   */
  protected function resetSequence(string $table, string $column = 'id'): void
  {
    if (DB::connection()->getDriverName() !== 'pgsql') {
      return;
    }

    DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', '{$column}'), COALESCE((SELECT MAX({$column}) FROM {$table}), 0) + 1, false)");
  }
}
