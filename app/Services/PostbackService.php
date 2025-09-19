<?php

namespace App\Services;

use App\Models\PostbackApiRequests;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Postback;
use Maxidev\Logger\TailLogger;
use App\Services\NaturalIntelligenceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class PostbackService
{
  public function __construct(protected NaturalIntelligenceService $niService) {}

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
    $clickId = $postback->click_id;
    $payout = $postback->payout;
    $offer_id = $postback->offer_id;

    TailLogger::saveLog("Initiating processed postback redirection", 'services/postback-redirect', 'info', [
      'postback_id' => $postback->id,
      'click_id' => $clickId,
      'payout' => $payout,
      'vendor' => $postback->vendor,
      'offer_id' => $offer_id
    ]);

    $offers = collect(config('offers.maxconv'));
    $offer = $offers->where('offer_id', $offer_id)->first() ?? null;

    if (!$offer) {
      TailLogger::saveLog("Offer not found", 'services/postback-redirect', 'warning', [
        'postback_id' => $postback->id,
        'vendor' => $postback->vendor,
        'offer_id' => $offer_id
      ]);
      return;
    }

    $postbackUrl = $offer['postback_url'];
    if (!$postbackUrl) {
      TailLogger::saveLog("No se encontró URL de redirección para el vendor", 'services/postback-redirect', 'warning', [
        'postback_id' => $postback->id,
        'vendor' => $postback->vendor
      ]);
      return;
    }

    // Preparar los datos para el postback
    $postbackData = [
      'click_id' => $clickId,
      'payout' => $payout,
      'transaction_id' => $postback->transaction_id,
      'currency' => $postback->currency,
      'event' => $postback->event,
    ];

    try {
      $startTime = microtime(true);
      $response = Http::timeout(30)->get($postbackUrl, $postbackData);
      $responseTime = (int) ((microtime(true) - $startTime) * 1000);

      // Registrar la petición en PostbackApiRequests
      PostbackApiRequests::create([
        'postback_id' => $postback->id,
        'request_id' => uniqid('req_'),
        'service' => 'max_conv',
        'endpoint' => $postbackUrl,
        'method' => 'GET',
        'request_data' => $postbackData,
        'response_data' => $response->body(),
        'status_code' => $response->status(),
        'response_time_ms' => $responseTime,
        'related_type' => PostbackApiRequests::RELATED_TYPE_POSTBACK_REDIRECT,
      ]);

      if ($response->successful()) {
        TailLogger::saveLog("Postback redirigido exitosamente", 'services/postback-redirect', 'success', [
          'postback_id' => $postback->id,
          'redirect_url' => $postbackUrl,
          'status_code' => $response->status(),
          'response_time_ms' => $responseTime
        ]);
      } else {
        TailLogger::saveLog("Error en la redirección del postback", 'services/postback-redirect', 'error', [
          'postback_id' => $postback->id,
          'redirect_url' => $postbackUrl,
          'status_code' => $response->status(),
          'response_body' => $response->body(),
          'response_time_ms' => $responseTime
        ]);
      }
    } catch (\Throwable $e) {
      TailLogger::saveLog("Excepción al redirigir postback", 'services/postback-redirect', 'error', [
        'postback_id' => $postback->id,
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      // Registrar el error en PostbackApiRequests
      PostbackApiRequests::create([
        'postback_id' => $postback->id,
        'service' => 'postback_redirect',
        'endpoint' => $postbackUrl ?? 'unknown',
        'request_data' => $postbackData ?? [],
        'response_data' => [
          'error' => $e->getMessage(),
          'trace' => $e->getTraceAsString()
        ],
        'status_code' => 0,
        'response_time_ms' => 0,
        'related_type' => 'postback_redirect_error',
        'related_id' => $postback->id
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
        $postback = Postback::create([
          'click_id' => $clickId,
          'offer_name' => $conversion['pub_param_2'] ?? 'N/A',
          'payout' => $conversion['payout'] ?? 0.0,
          'vendor' => 'ni', // Asumimos 'ni' ya que usamos NaturalIntelligenceService
          'status' => Postback::STATUS_PROCESSED, // Marcar como completado ya que tiene payout
          'created_at' => $reconciliationDate,
          'updated_at' => $reconciliationDate,
        ]);
        $createdCount++;
        $this->redirectPostback($postback);
        $processedCount++;
      }
      TailLogger::saveLog('PostbackService: Reconciliación completada', 'postback-reconciliation', 'success', [
        'date' => $date,
        'created' => $createdCount,
        'processed' => $processedCount,
      ]);

      return [
        'success' => true,
        'created' => $createdCount,
        'processed' => $processedCount,
      ];
    } catch (\Exception $e) {
      TailLogger::saveLog('PostbackService: Error durante la reconciliación', 'postback-reconciliation', 'error', [
        'date' => $date,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      return ['success' => false, 'message' => $e->getMessage()];
    }
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
