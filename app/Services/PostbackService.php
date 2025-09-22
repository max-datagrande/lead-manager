<?php

namespace App\Services;

use App\Models\PostbackApiRequests;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Postback;
use Maxidev\Logger\TailLogger;
use App\Services\NaturalIntelligenceService;
use App\Services\MaxconvService;
use Carbon\Carbon;

class PostbackService
{
  protected ?PostbackApiRequests $lastPostbackApiRequest = null;

  public function __construct(
    protected NaturalIntelligenceService $niService,
    protected MaxconvService $maxconvService
  ) {}

  public function getApiRequests(int $postbackId): Collection
  {
    return PostbackApiRequests::where('postback_id', $postbackId)
      ->orderBy('created_at', 'desc')
      ->get([
        'id',
        'request_id',
        'service',
        'endpoint',
        'method',
        'request_data',
        'response_data',
        'status_code',
        'error_message',
        'response_time_ms',
        'created_at'
      ]);
  }
  public function validatePostback(int $postbackId): Postback
  {
    $postback = Postback::find($postbackId);
    //Verificar si existe
    if (!$postback) {
      throw new PostbackServiceException("Postback not found", [
        'postback_id' => $postbackId,
        'exists' => $postback ? 'sí' : 'no',
        'status' => $postback?->status ?? 'N/A'
      ]);
    }
    //Verificar si esta procesado
    if ($postback->status === Postback::STATUS_PROCESSED) {
      throw new PostbackServiceException("Postback already marked as processed", [
        'postback_id' => $postbackId,
        'exists' => $postback ? 'sí' : 'no',
        'status' => $postback?->status ?? 'N/A'
      ]);
    }
    // Verificar si no está fallido
    if ($postback->status === Postback::STATUS_FAILED) {
      throw new PostbackServiceException("Postback already marked as failed", [
        'postback_id' => $postbackId,
        'exists' => $postback ? 'sí' : 'no',
        'status' => $postback?->status ?? 'N/A'
      ]);
    }
    TailLogger::saveLog("Postback found, current status: {$postback->status}", 'jobs/postback', 'info', [
      'postback_id' => $postbackId,
      'current_status' => $postback->status,
      'created_at' => $postback->created_at
    ]);
    return $postback;
  }
  /**
   * Redirige un postback procesado al vendor correspondiente.
   *
   * @param Postback $postback El postback a redirigir.
   * @return void
   */
  public function redirectPostback(Postback $postback): void
  {
    TailLogger::saveLog("Initiating processed postback redirection", 'services/postback-redirect', 'info', [
      'postback_id' => $postback->id,
      'click_id' => $postback->click_id,
      'payout' => $postback->payout,
      'vendor' => $postback->vendor,
      'offer_id' => $postback->offer_id
    ]);

    // Validar que la oferta existe antes de proceder
    if (!$this->maxconvService->offerExists($postback->offer_id)) {
      TailLogger::saveLog("Offer not found", 'services/postback-redirect', 'warning', [
        'postback_id' => $postback->id,
        'vendor' => $postback->vendor,
        'offer_id' => $postback->offer_id
      ]);
      return;
    }
    $postbackUrl = $this->maxconvService->buildPostbackUrl($postback);
    // Inicializar el registro de la petición API
    $newPostbackApiRequest = new PostbackApiRequests();
    $newPostbackApiRequest->postback_id = $postback->id;
    $newPostbackApiRequest->request_id = uniqid('req_');
    $newPostbackApiRequest->service = 'max_conv';
    $newPostbackApiRequest->endpoint = $postbackUrl;
    $newPostbackApiRequest->method = 'GET';
    $newPostbackApiRequest->request_data = null;
    $newPostbackApiRequest->response_data = null;
    $newPostbackApiRequest->status_code = null;
    $newPostbackApiRequest->response_time_ms = null;
    $newPostbackApiRequest->related_type = PostbackApiRequests::RELATED_TYPE_POSTBACK_REDIRECT;
    $newPostbackApiRequest->save();

    try {
      $startTime = microtime(true);
      // Delegar toda la lógica de Maxconv al servicio especializado
      $result = $this->maxconvService->processPostback($postback);

      // Actualizar el registro con los resultados
      $newPostbackApiRequest->endpoint = $result['url'];
      $newPostbackApiRequest->request_data = $result['data'];
      $newPostbackApiRequest->response_data = $result['response']->body();
      $newPostbackApiRequest->status_code = $result['response']->status();
      $newPostbackApiRequest->response_time_ms = (int) ((microtime(true) - $startTime) * 1000);
      $newPostbackApiRequest->update();

      //Update postback
      $postback->update([
        'status' => Postback::STATUS_PROCESSED,
        'processed_at' => now(),
        'response_data' => $result['response']->body(),
        'message' => 'Postback processed successfully',
      ]);
    } catch (\Throwable $e) {
      $errorContext = [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
      ];
      TailLogger::saveLog("Excepción al redirigir postback", 'services/postback-redirect', 'error', [ ...$errorContext, 'postback_id' => $postback->id ]);

      // Actualizar el registro con el error
      $newPostbackApiRequest->endpoint = 'error';
      $newPostbackApiRequest->request_data = $postback->toArray();
      $newPostbackApiRequest->response_data = $errorContext;
      $newPostbackApiRequest->status_code = 0;
      $newPostbackApiRequest->response_time_ms = (int) ((microtime(true) - $startTime) * 1000);
      $newPostbackApiRequest->update();

      //Actualizar postback
      $postback->update([
        'status' => Postback::STATUS_FAILED,
        'message' => $e->getMessage(),
        'response_data' => $errorContext,
      ]);
    }
  }

  /**
   * Reconcilia los payouts de un día específico desde Natural Intelligence.
   *
   * @param string $date La fecha en formato Y-m-d.
   * @return array Un resumen de las operaciones.
   */
  public function reconcileDailyPayouts(string $date): array
  {
    $startTime = microtime(true);
    TailLogger::saveLog('PostbackService: Iniciando reconciliación de payouts', 'postback-reconciliation', 'info', [
      'date' => $date,
    ]);

    try {
      $reportResult = $this->niService->getReportForReconciliation($date, $date);
      $wasSuccessful = $reportResult['success'] ?? false;
      if (!$wasSuccessful) {
        TailLogger::saveLog('PostbackService: No se pudo obtener el reporte de NI', 'postback-reconciliation', 'error', [
          'date' => $date,
          'result' => $reportResult
        ]);
        return ['success' => false, 'message' => 'Failed to get report from NI.'];
      }
      $conversions = $reportResult['data'] ?? [];

      if (empty($conversions)) {
        TailLogger::saveLog('PostbackService: No se encontraron conversiones en el reporte para la fecha', 'postback-reconciliation', 'warning', [
          'date' => $date,
        ]);
        return ['success' => true, 'created' => 0, 'ignored' => 0, 'message' => 'No conversions found in the report for the given date.'];
      }

      $createdCount = 0;
      $existingAndProcessedCount = 0;
      $processedCount = 0;
      $reconciliationDate = Carbon::parse($date)->startOfDay();
      TailLogger::saveLog('PostbackService: Reconciling conversions for date', 'postback-reconciliation', 'info', [
        'date' => $date,
        'total_conversions' => count($conversions),
        'details' => $conversions
      ]);
      foreach ($conversions as $conversion) {
        $clickId = $conversion['pub_param_1'] ?? null;
        if (!$clickId) {
          continue; // Ignorar si no hay click_id
        }
        $existingPostback = Postback::where('click_id', $clickId)->first();
        if ($existingPostback) {
          $responseData = [
            'postback_id' => $existingPostback->id,
            'click_id' => $clickId,
            'payout' => $conversion['payout'] ?? 0.0,
            'total_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
            'response_time_ms' => (int) ((microtime(true) - $startTime) * 1000)
          ];
          $existingPostback->markAsProcessed($responseData);
          TailLogger::saveLog('PostbackService: Postback ya existe, marcando como procesado', 'postback-reconciliation', 'info', $responseData);
          $this->redirectPostback($existingPostback);
          $existingAndProcessedCount++;
          $processedCount++;
          continue; // Ignorar si ya existe
        }
        $offerData = $this->getOfferData($conversion);
        if (!$offerData) {
          TailLogger::saveLog('PostbackService: No se encontró información de oferta para el click ID', 'postback-reconciliation', 'warning', [
            'click_id' => $clickId,
            'conversion' => $conversion
          ]);
          continue;
        }

        // Crear la instancia sin guardar aún
        $postback = new Postback([
          'click_id' => $clickId,
          'offer_id' => $offerData['offer_id'],
          'event' => $offerData['offer_event'],
          'payout' => $conversion['payout'] ?? 0.0,
          'currency' => 'USD',
          'vendor' => 'ni', // Asumimos 'ni' ya que usamos NaturalIntelligenceService
          'status' => Postback::STATUS_PENDING, // Marcar como pendiente
          'message' => 'Pending verification',
        ]);
        // Deshabilitar timestamps automáticos temporalmente para esta instancia
        $postback->timestamps = false;

        // Setear fechas personalizadas (usa Carbon si necesitas parsear)
        $postback->created_at = Carbon::parse($reconciliationDate);
        $postback->updated_at = Carbon::parse($reconciliationDate);

        // Guardar el registro (ahora con tus fechas)
        $postback->save();

        //Increase counters
        $createdCount++;
        $this->redirectPostback($postback);
        $processedCount++;
      }
      TailLogger::saveLog('PostbackService: Reconciliación completada', 'postback-reconciliation', 'success', [
        'date' => $date,
        'created' => $createdCount,
        'processed' => $processedCount,
        'total_conversions' => count($conversions),
        'updated' => $existingAndProcessedCount,
      ]);

      return [
        'success' => true,
        'created' => $createdCount,
        'processed' => $processedCount,
        'total_conversions' => count($conversions),
        'updated' => $existingAndProcessedCount,
      ];
    } catch (\Exception $e) {
      TailLogger::saveLog('PostbackService: Error during reconciliation', 'postback-reconciliation', 'error', [
        'date' => $date,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
      ]);
      return ['success' => false, 'message' => $e->getMessage()];
    }
  }
  public function getOfferData(array $conversion): ?array
  {
    try {
      $offerData = $this->niService->getOfferData($conversion);
    } catch (\Throwable $th) {
      return null;
    }
    return $offerData;
  }
}

class PostbackServiceException extends \Exception
{
  protected array $context = [];
  public function __construct(string $message, array $context = [], int $code = 0, ?\Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
    $this->context = $context;
  }
  public function getContext(): array
  {
    return $this->context;
  }
}
