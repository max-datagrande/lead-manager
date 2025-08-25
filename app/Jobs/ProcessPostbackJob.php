<?php

namespace App\Jobs;

use App\Models\Postback;
use App\Services\NaturalIntelligenceService;
use App\Services\PayoutNotFoundException;
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

  public $tries = 18; // Número máximo de intentos
  public $backoff = 14400; // Reintentos: 5min, 15min, 1hora
  public $timeout = 120; // Timeout de 2 minutos
  public $maxExceptions = 1; // Solo fallar en errores inesperados

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

    TailLogger::saveLog("Iniciando procesamiento de postback ID: {$this->postbackId}, Click ID: {$this->clickId}", 'jobs/postback', 'info', [
      'postback_id' => $this->postbackId,
      'click_id' => $this->clickId,
      'attempt' => $this->attempts(),
      'max_tries' => $this->tries
    ]);

    try {
      // Buscar el postback
      TailLogger::saveLog("Buscando postback en base de datos", 'jobs/postback', 'debug', [
        'postback_id' => $this->postbackId
      ]);

      $postback = Postback::find($this->postbackId);

      // Verificar si existe y no está fallido
      if (!$postback || $postback->status === Postback::STATUS_FAILED) {
        TailLogger::saveLog("Postback no encontrado o ya está marcado como fallido", 'jobs/postback', 'warning', [
          'postback_id' => $this->postbackId,
          'exists' => $postback ? 'sí' : 'no',
          'status' => $postback?->status ?? 'N/A'
        ]);
        return;
      }

      TailLogger::saveLog("Postback encontrado, estado actual: {$postback->status}", 'jobs/postback', 'info', [
        'postback_id' => $this->postbackId,
        'current_status' => $postback->status,
        'created_at' => $postback->created_at
      ]);

      // Obtener payout específico para este clickId usando el servicio mejorado
      TailLogger::saveLog("Consultando payout en Natural Intelligence", 'jobs/postback', 'info', [
        'click_id' => $this->clickId,
        'service' => 'NaturalIntelligenceService'
      ]);

      $niService = app(NaturalIntelligenceService::class);
      $payout = $niService->getPayoutForClickId($this->clickId, $postback);

      $responseTime = (int) ((microtime(true) - $startTime) * 1000);

      if ($payout === null) {
        TailLogger::saveLog("Payout no encontrado para el click ID", 'jobs/postback', 'warning', [
          'click_id' => $this->clickId,
          'response_time_ms' => $responseTime
        ]);
        throw new Exception('Payout not found for click ID: ' . $this->clickId);
      }

      // Actualizar postback con el payout obtenido
      TailLogger::saveLog("Actualizando postback con payout obtenido", 'jobs/postback', 'info', [
        'postback_id' => $this->postbackId,
        'payout' => $payout,
        'new_status' => Postback::STATUS_PROCESSED
      ]);

      $postback->update([
        'payout' => $payout,
        'status' => Postback::STATUS_PROCESSED,
        'processed_at' => Carbon::now(),
      ]);

      TailLogger::saveLog("Postback procesado exitosamente", 'jobs/postback', 'success', [
        'postback_id' => $this->postbackId,
        'click_id' => $this->clickId,
        'payout' => $payout,
        'total_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
        'attempt' => $this->attempts()
      ]);
    } catch (PayoutNotFoundException $e) {
      $responseTime = (int) ((microtime(true) - $startTime) * 1000);

      TailLogger::saveLog("Payout no encontrado, programando reintento", 'jobs/postback', 'warning', [
        'postback_id' => $this->postbackId,
        'click_id' => $this->clickId,
        'attempt' => $this->attempts(),
        'max_tries' => $this->tries,
        'next_retry_in' => '4 horas',
        'response_time_ms' => $responseTime,
        'exception' => $e->getMessage()
      ]);

      throw $e; // Reintento en 4 horas

    } catch (Exception $e) {
      $responseTime = (int) ((microtime(true) - $startTime) * 1000);

      TailLogger::saveLog('Error inesperado al procesar postback, marcando como fallido', 'jobs/postback', 'error', [
        'postback_id' => $this->postbackId,
        'click_id' => $this->clickId,
        'attempt' => $this->attempts(),
        'response_time_ms' => $responseTime,
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);

      if (isset($postback)) {
        $postback->markAsFailed();
        TailLogger::saveLog("Postback marcado como fallido en base de datos", 'jobs/postback', 'info', [
          'postback_id' => $this->postbackId,
          'final_status' => Postback::STATUS_FAILED
        ]);
      }

      $this->fail($e); // Esto marca el job como fallido permanentemente
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
