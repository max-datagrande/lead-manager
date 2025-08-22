<?php

namespace App\Jobs;

use App\Models\Postback;
use App\Services\NaturalIntelligenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maxidev\Logger\TailLogger;
use Carbon\Carbon;
use Exception;

class ProcessPostbackJob implements ShouldQueue
{
  use Queueable, InteractsWithQueue, SerializesModels;

  public $tries = 5; // Número máximo de intentos
  public $backoff = [300, 900, 3600]; // Reintentos: 5min, 15min, 1hora
  public $timeout = 120; // Timeout de 2 minutos

  protected $postbackId;
  protected $clickId;

  /**
   * Create a new job instance.
   */
  public function __construct(int $postbackId, string $clickId)
  {
    $this->postbackId = $postbackId;
    $this->clickId = $clickId;
  }

  /**
   * Execute the job.
   */
  public function handle(): void
  {
    $startTime = microtime(true);

    try {
      // Buscar el postback
      $postback = Postback::find($this->postbackId);
      // Verificar si existe y no está fallido
      if (!$postback || $postback->status === Postback::STATUS_FAILED) {
        TailLogger::saveLog('ProcessPostbackJob: Postback no encontrado o fallido', 'jobs/postback', 'error', [
          'postback_id' => $this->postbackId
        ]);
        return;
      }


      TailLogger::saveLog('ProcessPostbackJob: Iniciando procesamiento', 'jobs/postback', 'info', [
        'postback_id' => $this->postbackId,
        'click_id' => $this->clickId,
        'attempt' => $this->attempts()
      ]);

      // Obtener payout específico para este clickId usando el servicio mejorado
      $niService = new NaturalIntelligenceService();
      $reportResult = $niService->getRecentConversionsReport();

      if (!$reportResult['success']) {
        TailLogger::saveLog('NI Service: Error al obtener reporte reciente', 'api/ni', 'error', [
          'clickId' => $this->clickId,
          'error' => $reportResult['message'] ?? 'Unknown error'
        ]);
        //Set the status to failed
        $postback->markAsFailed();
        return;
      }
      $conversions = $reportResult['data'] ?? [];
      if (empty($conversions)) {
        TailLogger::saveLog('NI Service: No hay conversiones en el reporte', 'api/ni', 'warning', [
          'clickId' => $this->clickId
        ]);

        return;
      }


      $payout = $niService->getPayoutForClickId($this->clickId);

      $responseTime = (int)((microtime(true) - $startTime) * 1000);
      if ($payout === null) {
        throw new Exception('Payout not found for click ID: ' . $this->clickId);
      }

      // Actualizar postback con el payout obtenido
      $postback->update([
        'payout' => $payout,
        'status' => Postback::STATUS_PROCESSED,
        'processed_at' => Carbon::now()
      ]);

      TailLogger::saveLog('ProcessPostbackJob: Postback procesado exitosamente', 'jobs/postback', 'info', [
        'postback_id' => $this->postbackId,
        'click_id' => $this->clickId,
        'payout' => $payout,
        'response_time_ms' => $responseTime
      ]);
    } catch (Exception $e) {
      $responseTime = (int)((microtime(true) - $startTime) * 1000);

      TailLogger::saveLog('ProcessPostbackJob: Error en procesamiento', 'jobs/postback', 'error', [
        'postback_id' => $this->postbackId,
        'click_id' => $this->clickId,
        'error' => $e->getMessage(),
        'attempt' => $this->attempts(),
        'max_tries' => $this->tries,
        'response_time_ms' => $responseTime
      ]);

      // Si es el último intento, marcar como fallido
      if ($this->attempts() >= $this->tries) {
        $postback = Postback::find($this->postbackId);
        if ($postback) {
          $postback->markAsFailed();
        }

        TailLogger::saveLog('ProcessPostbackJob: Postback marcado como fallido después de todos los intentos', 'jobs/postback', 'error', [
          'postback_id' => $this->postbackId,
          'click_id' => $this->clickId,
          'final_error' => $e->getMessage()
        ]);
      }

      throw $e; // Re-lanzar para que el sistema de colas maneje el reintento
    }
  }

  /**
   * Calculate the number of seconds to wait before retrying the job.
   */
  public function backoff(): array
  {
    // Intervalos configurables: 5 minutos, 15 minutos, 1 hora
    return [
      config('queue.postback_retry_intervals.first', 300),    // 5 minutos
      config('queue.postback_retry_intervals.second', 900),   // 15 minutos
      config('queue.postback_retry_intervals.third', 3600),   // 1 hora
    ];
  }

  /**
   * Handle a job failure.
   */
  public function failed(Exception $exception): void
  {
    TailLogger::saveLog('ProcessPostbackJob: Job falló definitivamente', 'jobs/postback', 'error', [
      'postback_id' => $this->postbackId,
      'click_id' => $this->clickId,
      'exception' => $exception->getMessage(),
      'trace' => $exception->getTraceAsString()
    ]);

    // Marcar postback como fallido
    $postback = Postback::find($this->postbackId);
    if ($postback) {
      $postback->markAsFailed();
    }
  }
}
