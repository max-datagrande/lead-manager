<?php

namespace App\Jobs;

use App\Models\Postback;
use App\Services\NaturalIntelligenceService;
use App\Services\PostbackService;
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
        TailLogger::saveLog('Postback not found or already failed : ' . $this->postbackId, 'job/postback');
        return;
      }

      // Obtener payout específico para este clickId usando el servicio mejorado
      $niService = app(NaturalIntelligenceService::class);
      $reportResult = $niService->getRecentConversionsReport($postback);

      if (!$reportResult['success']) {
        //Set the status to failed
        $postback->markAsFailed();
        return;
      }
      $conversions = $reportResult['data'] ?? [];
      if (empty($conversions)) {
        return;
      }

      $payout = $niService->getPayoutForClickId($this->clickId);

      $responseTime = (int) ((microtime(true) - $startTime) * 1000);
      if ($payout === null) {
        throw new Exception('Payout not found for click ID: ' . $this->clickId);
      }

      // Actualizar postback con el payout obtenido
      $postback->update([
        'payout' => $payout,
        'status' => Postback::STATUS_PROCESSED,
        'processed_at' => Carbon::now(),
      ]);
    } catch (Exception $e) {
      $responseTime = (int) ((microtime(true) - $startTime) * 1000);
      // Si es el último intento, marcar como fallido
      if ($this->attempts() >= $this->tries) {
        $postback = Postback::find($this->postbackId);
        if ($postback) {
          $postback->markAsFailed();
        }
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
      config('queue.postback_retry_intervals.first', 300), // 5 minutos
      config('queue.postback_retry_intervals.second', 900), // 15 minutos
      config('queue.postback_retry_intervals.third', 3600), // 1 hora
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
      'trace' => $exception->getTraceAsString(),
    ]);

    // Marcar postback como fallido
    $postback = Postback::find($this->postbackId);
    if ($postback) {
      $postback->markAsFailed();
    }
  }
}
