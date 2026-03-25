<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    // 1. Add env_type column if it doesn't exist yet
    $columnAdded = false;
    if (!Schema::hasColumn('integration_environments', 'env_type')) {
      Schema::table('integration_environments', function (Blueprint $table) {
        $table->string('env_type', 20)->default('offerwall')->after('environment')
          ->comment('ping|post|offerwall');
      });
      $columnAdded = true;
    }

    // 2. Backfill only when the column was just created (safe — no existing env_type data)
    //    For offerwall rows: default 'offerwall' already applied.
    //    For post-only integrations: set to 'post'.
    //    ping-post integrations need manual env_type assignment since we can't
    //    infer ping vs post from the row alone.
    if ($columnAdded) {
      DB::statement("
                UPDATE integration_environments ie
                SET env_type = 'post'
                FROM integrations i
                WHERE ie.integration_id = i.id
                  AND i.type = 'post-only'
            ");
    }

    // 3. Drop old unique constraint (integration_id, environment) if it still exists
    if (Schema::hasIndex('integration_environments', 'integration_environments_integration_id_environment_unique')) {
      Schema::table('integration_environments', function (Blueprint $table) {
        $table->dropUnique(['integration_id', 'environment']);
      });
    }

    // 4. Add new unique constraint if it doesn't exist yet
    if (!Schema::hasIndex('integration_environments', ['integration_id', 'environment', 'env_type'], 'unique')) {
      Schema::table('integration_environments', function (Blueprint $table) {
        $table->unique(['integration_id', 'environment', 'env_type']);
      });
    }
  }

  public function down(): void
  {
    Schema::table('integration_environments', function (Blueprint $table) {
      $table->dropUnique(['integration_id', 'environment', 'env_type']);
    });

    Schema::table('integration_environments', function (Blueprint $table) {
      $table->unique(['integration_id', 'environment']);
    });

    Schema::table('integration_environments', function (Blueprint $table) {
      $table->dropColumn('env_type');
    });
  }
};
