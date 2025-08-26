<?php

namespace App\Jobs;

use App\Events\PostbackProcessed;
use App\Models\Postback;
use App\Services\NaturalIntelligenceService;
use App\Services\PayoutNotFoundException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maxidev\Logger\TailLogger;
use Carbon\Carbon;
use Throwable;

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
      $niService->setPostbackId($this->postbackId);
      $payout = $niService->getPayoutForClickId($this->clickId);
      $responseTime = (int) ((microtime(true) - $startTime) * 1000);

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

      // Disparar evento de postback procesado
      event(new PostbackProcessed($postback));

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

      // Programar reintento en 4 horas sin lanzar excepción
      $this->release($this->backoff); // 14400 segundos = 4 horas
      return;
    } catch (\Throwable $e) {
      $responseTime = (int) ((microtime(true) - $startTime) * 1000);
      $responseData = [
        'postback_id' => $this->postbackId,
        'click_id' => $this->clickId,
        'attempt' => $this->attempts(),
        'response_time_ms' => $responseTime,
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ];
      TailLogger::saveLog('Error inesperado al procesar postback, marcando como fallido', 'jobs/postback', 'error', $responseData);

      if (isset($postback)) {
        TailLogger::saveLog("Postback marcado como fallido en base de datos", 'jobs/postback', 'info', [
          'postback_id' => $this->postbackId,
          'final_status' => Postback::STATUS_FAILED
        ]);
        $postback->markAsFailed($e->getMessage(), $responseData);
      }

      $this->fail($e); // Esto marca el job como fallido permanentemente
    }
  }
  public function failed(Throwable $exception): void
  {
    $postback = Postback::find($this->postbackId);
    if ($postback && $postback->isPending()) {
      $reason = 'Job failed after all retries: ' . $exception->getMessage();

      // Si el error original fue PayoutNotFound, el mensaje es más claro
      if ($exception instanceof PayoutNotFoundException) {
        $reason = 'Unable to find payout after 3 days.';
      }
      $postback->markAsFailed($reason);
      TailLogger::saveLog('Job failed permanently and postback marked as failed', 'jobs/postback', 'error', [
        'postback_id' => $this->postbackId,
        'reason' => $reason,
      ]);
    }
  }
}
