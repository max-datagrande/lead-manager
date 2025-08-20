<?php

namespace App\Console\Commands;

use App\Models\TrafficLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessCampaignCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'traffic:process-campaign-codes 
                            {--batch-size=10000 : Number of records to process per batch}
                            {--dry-run : Run without making changes to see what would be processed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes existing traffic logs to extract campaign_code from query_params.cptype';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $batchSize = (int) $this->option('batch-size');
        $isDryRun = $this->option('dry-run');
        
        $this->info("Starting campaign code processing...");
        $this->info("Lot size: {$batchSize}");
        
        if ($isDryRun) {
            $this->warn("DRY-RUN MODE: No changes will be made to the database.");
        }

        // Contar total de registros que necesitan procesamiento
        $totalRecords = TrafficLog::whereNull('campaign_code')
            ->whereNotNull('query_params')
            ->count();

        if ($totalRecords === 0) {
            $this->info("There are no records to process.");
            return Command::SUCCESS;
        }

        $this->info("Total records to be processed: {$totalRecords}");
        
        $processedCount = 0;
        $updatedCount = 0;
        $offset = 0;

        // Crear barra de progreso
        $progressBar = $this->output->createProgressBar($totalRecords);
        $progressBar->start();

        do {
            // Obtener lote de registros
            $records = TrafficLog::whereNull('campaign_code')
                ->whereNotNull('query_params')
                ->offset($offset)
                ->limit($batchSize)
                ->get();

            if ($records->isEmpty()) {
                break;
            }

            // Procesar cada registro en el lote
            foreach ($records as $record) {
                $queryParams = $record->query_params;
                
                // Verificar si query_params es un array o JSON string
                if (is_string($queryParams)) {
                    $queryParams = json_decode($queryParams, true);
                }

                // Extraer campaign_code si existe cptype
                if (is_array($queryParams) && isset($queryParams['cptype'])) {
                    $campaignCode = $queryParams['cptype'];
                    
                    if (!$isDryRun) {
                        // Actualizar el registro
                        $record->update(['campaign_code' => $campaignCode]);
                        $updatedCount++;
                    } else {
                        // En modo dry-run, solo mostrar qué se haría
                        $this->line("\nID: {$record->id} - Campaign Code: {$campaignCode}");
                        $updatedCount++;
                    }
                }

                $processedCount++;
                $progressBar->advance();
            }

            $offset += $batchSize;
            
            // Pequeña pausa para no sobrecargar la base de datos
            usleep(100000); // 0.1 segundos

        } while ($records->count() === $batchSize);

        $progressBar->finish();
        $this->newLine(2);

        // Mostrar resumen
        $this->info("Processing completed:");
        $this->info("- Processed records: {$processedCount}");
        $this->info("- Updated records: {$updatedCount}");
        
        if ($isDryRun) {
            $this->warn("Run without --dry-run to apply changes.");
        }

        return Command::SUCCESS;
    }
}
