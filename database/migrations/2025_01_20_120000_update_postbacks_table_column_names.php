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
        if (! Schema::hasTable('postbacks')) {
            return;
        }

        $indexNames = collect(Schema::getIndexes('postbacks'))->pluck('name')->values()->all();

        Schema::table('postbacks', function (Blueprint $table) use ($indexNames) {
            // 1. Eliminar índices antiguos si existen
            if (in_array('postbacks_clid_index', $indexNames, true)) {
                $table->dropIndex('postbacks_clid_index');
            }

            if (in_array('postbacks_vendor_txid_unique', $indexNames, true)) {
                $table->dropUnique('postbacks_vendor_txid_unique');
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
            if (! Schema::hasColumn('postbacks', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('status');
            }

            if (! Schema::hasColumn('postbacks', 'external_campaign_id')) {
                $table->string('external_campaign_id')->nullable()->after('failure_reason');
            }

            if (! Schema::hasColumn('postbacks', 'external_traffic_source')) {
                $table->string('external_traffic_source')->nullable()->after('external_campaign_id');
            }
        });

        $currentIndexNames = collect(Schema::getIndexes('postbacks'))->pluck('name')->values()->all();

        Schema::table('postbacks', function (Blueprint $table) use ($currentIndexNames) {
            // 4. Crear nuevos índices con nombres correctos
            if (! in_array('postbacks_click_id_index', $currentIndexNames, true)) {
                $table->index(['click_id']);
            }

            if (! in_array('postbacks_vendor_transaction_id_unique', $currentIndexNames, true)) {
                $table->unique(['vendor', 'transaction_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('postbacks')) {
            return;
        }

        $indexNames = collect(Schema::getIndexes('postbacks'))->pluck('name')->values()->all();

        Schema::table('postbacks', function (Blueprint $table) use ($indexNames) {
            // Eliminar índices nuevos
            if (in_array('postbacks_click_id_index', $indexNames, true)) {
                $table->dropIndex('postbacks_click_id_index');
            }

            if (in_array('postbacks_vendor_transaction_id_unique', $indexNames, true)) {
                $table->dropUnique('postbacks_vendor_transaction_id_unique');
            }

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

        $currentIndexNames = collect(Schema::getIndexes('postbacks'))->pluck('name')->values()->all();

        Schema::table('postbacks', function (Blueprint $table) use ($currentIndexNames) {
            // Recrear índices antiguos
            if (! in_array('postbacks_clid_index', $currentIndexNames, true)) {
                $table->index(['clid']);
            }

            if (! in_array('postbacks_vendor_txid_unique', $currentIndexNames, true)) {
                $table->unique(['vendor', 'txid']);
            }
        });
    }
};
