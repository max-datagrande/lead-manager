<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('postbacks', function (Blueprint $table) {
            // 1. Eliminar índices antiguos si existen
            try {
                $table->dropIndex(['clid']); // Eliminar índice de clid
            } catch (Exception $e) {
                // Índice no existe, continuar
            }
            
            try {
                $table->dropUnique(['vendor', 'txid']); // Eliminar índice único de vendor+txid
            } catch (Exception $e) {
                // Índice no existe, continuar
            }
        });

        // 2. Renombrar columnas si aún tienen nombres antiguos
        if (Schema::hasColumn('postbacks', 'clid')) {
            Schema::table('postbacks', function (Blueprint $table) {
                $table->renameColumn('clid', 'click_id');
            });
        }

        if (Schema::hasColumn('postbacks', 'txid')) {
            Schema::table('postbacks', function (Blueprint $table) {
                $table->renameColumn('txid', 'transaction_id');
            });
        }

        Schema::table('postbacks', function (Blueprint $table) {
            // 3. Agregar columnas faltantes si no existen
            if (!Schema::hasColumn('postbacks', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('status');
            }
            
            if (!Schema::hasColumn('postbacks', 'external_campaign_id')) {
                $table->string('external_campaign_id')->nullable()->after('failure_reason');
            }
            
            if (!Schema::hasColumn('postbacks', 'external_traffic_source')) {
                $table->string('external_traffic_source')->nullable()->after('external_campaign_id');
            }

            // 4. Crear nuevos índices con nombres correctos
            $table->index(['click_id']); // Índice para click_id
            $table->unique(['vendor', 'transaction_id']); // Índice único para vendor+transaction_id
            $table->unique(['vendor', 'click_id']); // Índice único para vendor+click_id
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('postbacks', function (Blueprint $table) {
            // Eliminar índices nuevos
            $table->dropIndex(['click_id']);
            $table->dropUnique(['vendor', 'transaction_id']);
            
            // Eliminar columnas agregadas
            if (Schema::hasColumn('postbacks', 'external_traffic_source')) {
                $table->dropColumn('external_traffic_source');
            }
            
            if (Schema::hasColumn('postbacks', 'external_campaign_id')) {
                $table->dropColumn('external_campaign_id');
            }
            
            if (Schema::hasColumn('postbacks', 'failure_reason')) {
                $table->dropColumn('failure_reason');
            }
        });

        // Renombrar columnas de vuelta
        if (Schema::hasColumn('postbacks', 'transaction_id')) {
            Schema::table('postbacks', function (Blueprint $table) {
                $table->renameColumn('transaction_id', 'txid');
            });
        }

        if (Schema::hasColumn('postbacks', 'click_id')) {
            Schema::table('postbacks', function (Blueprint $table) {
                $table->renameColumn('click_id', 'clid');
            });
        }

        Schema::table('postbacks', function (Blueprint $table) {
            // Recrear índices antiguos
            $table->index(['clid']);
            $table->unique(['vendor', 'txid']);
        });
    }
};