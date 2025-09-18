<?php

namespace App\Jobs;

use App\Events\PostbackProcessed;
use App\Models\Postback;
use App\Services\PostbackService;
use App\Services\PostbackServiceException;
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
  public $backoff = 14400; // Reintentos
  public $timeout = 120; // Timeout de 2 minutos
  public $maxExceptions = 1; // Solo fallar en errores inesperados

  protected $postbackId;
  protected $clickId;
  protected $postbackService;

  protected $startTime;
  protected $responseTime;

  /**
   * Create a new job instance.
   */
  public function __construct(int $postbackId, string $clickId)
  {
    $this->postbackId = $postbackId;
    $this->clickId = $clickId;
    $this->postbackService = app(PostbackService::class);
  }

  /**
   * Execute the job.
   */
  public function handle(): void
  {
    $this->startTime = microtime(true);
    TailLogger::saveLog("Iniciando procesamiento de postback ID: {$this->postbackId}, Click ID: {$this->clickId}", 'jobs/postback', 'info', [
      'postback_id' => $this->postbackId,
      'click_id' => $this->clickId,
      'attempt' => $this->attempts(),
      'max_tries' => $this->tries
    ]);
    // Buscar el postback
    TailLogger::saveLog("Buscando postback en base de datos", 'jobs/postback', 'debug', [
      'postback_id' => $this->postbackId
    ]);
    try {
      $postback = $this->postbackService->validatePostback($this->postbackId);
      // Obtener payout específico para este clickId usando el servicio mejorado
      TailLogger::saveLog("Consultando payout en Natural Intelligence", 'jobs/postback', 'info', [
        'click_id' => $this->clickId,
        'service' => 'NaturalIntelligenceService'
      ]);

      $niService = app(NaturalIntelligenceService::class);
      $niService->setPostbackId($this->postbackId);
      $payout = $niService->getPayoutForClickId($this->clickId);

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
        'total_time_ms' => (int) ((microtime(true) - $this->startTime) * 1000),
        'attempt' => $this->attempts()
      ]);
    } catch (PostbackServiceException $e) {
      $context = $e->getContext();
      TailLogger::saveLog("Error validating postback: {$e->getMessage()}", 'jobs/postback', 'error', $context);
      return;
    } catch (PayoutNotFoundException $e) {
      TailLogger::saveLog("Payout no encontrado, programando reintento", 'jobs/postback', 'warning', [
        'postback_id' => $this->postbackId,
        'click_id' => $this->clickId,
        'attempt' => $this->attempts(),
        'max_tries' => $this->tries,
        'next_retry_in' => '4 horas',
        'response_time_ms' => $this->getResponseTime(),
        'exception' => $e->getMessage()
      ]);
      $lastTry = $this->attempts() === $this->tries;
      if ($lastTry) {
        throw $e;
      }
      // Programar reintento en 4 horas sin lanzar excepción
      $this->release($this->backoff); // 14400 segundos = 4 horas
      return;
    }
  }

  public function failed(Throwable $exception): void
  {
    $responseTime = $this->getResponseTime();
    $responseData = [
      'postback_id' => $this->postbackId,
      'click_id' => $this->clickId,
      'attempt' => $this->attempts(),
      'response_time_ms' => $responseTime,
      'exception' => $exception->getMessage(),
      'trace' => $exception->getTraceAsString(),
    ];
    TailLogger::saveLog('Error inesperado al procesar postback, marcando como fallido', 'jobs/postback', 'error', $responseData);

    $postback = Postback::find($this->postbackId);
    $stillPending = $postback && $postback->isPending();
    $reason = "Unexpected error when processing postback, marking as failed - {$exception->getMessage()}";
    if ($stillPending) {
      $reason = "Job failed after all retries: {$exception->getMessage()}";
    }
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

  public function getResponseTime(): int
  {
    $this->responseTime = (int) ((microtime(true) - $this->startTime) * 1000);
    return $this->responseTime;
  }
}
