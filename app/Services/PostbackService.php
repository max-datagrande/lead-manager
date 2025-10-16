<?php

namespace App\Services;

use App\Models\PostbackApiRequests;
use Illuminate\Database\Eloquent\Collection;
use App\Events\PostbackProcessed;
use App\Models\Postback;
/* use App\Enums\PostbackVendor; */
use Maxidev\Logger\TailLogger;
use App\Services\MaxconvService;
use App\Services\Postback\VendorServiceResolver;
/* use Carbon\Carbon; */
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PostbackService
{
  protected ?PostbackApiRequests $lastPostbackApiRequest = null;

  public function __construct(
    protected VendorServiceResolver $vendorResolver,
    protected MaxconvService $maxconvService
  ) {}

  public function searchPayout(string $clickId, string $vendor, ?string $fromDate, ?string $toDate, bool $returnConversionObject = false): float|array|null
  {
    $vendorService = $this->vendorResolver->make($vendor);
    return $vendorService->getPayoutForClickId($clickId, $fromDate, $toDate, $returnConversionObject);
  }

  public function isVendorRegistered(string $vendor): bool
  {
    return $this->vendorResolver->isRegistered($vendor);
  }

  /**
   * Force sync a single postback against the vendor report.
   *
   * @param Postback $postback
   * @return array
   */
  public function forceSyncPostback(Postback $postback): void
  {
    // 1. Calculate Date Range
    $createdAt = $postback->created_at;
    $fromDate = $createdAt->copy()->subDay()->format('Y-m-d');
    $toDate = $createdAt->copy()->addDays(3)->format('Y-m-d');

    // 2. Call searchPayout with the specific range and request the full conversion object
    $conversionData = $this->searchPayout($postback->click_id, $postback->vendor, $fromDate, $toDate, true);

    // 3. Handle result
    // 3b. Failure: Payout not found (searchPayout returned null)
    if (!is_array($conversionData)) {
      $postback->update([
        'status' => Postback::statusFailed(),
        'message' => "Payout not found in vendor report during force sync on " . now()->toDateTimeString(),
        'processed_at' => now(),
        'response_data' => ['error' => 'Payout not found in vendor report.'],
      ]);

      TailLogger::saveLog('Postback force sync failed: Payout not found.', 'postback/force-sync', 'warning', [
        'postback_id' => $postback->id,
        'searched_from' => $fromDate,
        'searched_to' => $toDate,
      ]);

      add_flash_message(type: "error", message: "Payout not found in the vendor report for the specified period.");
      return;
    }

    $payout = $conversionData['payout'] ?? null;

    // Handles payout == 0 or payout key not present in conversion
    if ($payout <= 0) {
      $postback->update([
        'payout' => 0,
        'status' => Postback::statusSkipped(),
        'message' => 'Payout was 0 or null, postback skipped during force sync on ' . now()->toDateTimeString(),
        'processed_at' => now(),
        'response_data' => $conversionData,
      ]);

      TailLogger::saveLog('Postback force sync skipped: Payout was 0 or null.', 'postback/force-sync', 'info', [
        'postback_id' => $postback->id,
      ]);

      add_flash_message(type: "info", message: "Sync complete. Payout was 0 or null, so postback was skipped.");
      return;
    }

    // 3a. Success: Payout found
    $postback->update([
      'payout' => $payout,
      'message' => "Payout {$payout} found via force sync on " . now()->toDateTimeString(),
      'processed_at' => now(),
      'response_data' => $conversionData,
    ]);

    // 4. Dispatch Event
    event(new PostbackProcessed($postback));

    TailLogger::saveLog('Postback force sync successful.', 'postback/force-sync', 'info', [
      'postback_id' => $postback->id,
      'payout' => $payout,
    ]);

    add_flash_message(type: "success", message: "Sync successful. Payout found: {$payout}");
    return;
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
      $json = $result['response']->json();
      $body =  $result['response']->body();
      $postback->update([
        'status' => Postback::statusProcessed(),
        'processed_at' => now(),
        'response_data' => $json ?? $body,
        'message' => 'Postback processed successfully',
      ]);
    } catch (\Throwable $e) {
      $errorContext = [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
      ];
      TailLogger::saveLog("Excepción al redirigir postback", 'services/postback-redirect', 'error', [...$errorContext, 'postback_id' => $postback->id]);

      // Actualizar el registro con el error
      $newPostbackApiRequest->endpoint = 'error';
      $newPostbackApiRequest->request_data = $postback->toArray();
      $newPostbackApiRequest->response_data = $errorContext;
      $newPostbackApiRequest->status_code = 0;
      $newPostbackApiRequest->response_time_ms = (int) ((microtime(true) - $startTime) * 1000);
      $newPostbackApiRequest->update();

      //Actualizar postback
      $postback->update([
        'status' => Postback::statusFailed(),
        'message' => $e->getMessage(),
        'response_data' => $errorContext,
      ]);
    }
  }

  public function getOffers(): Collection
  {
    return collect(config('offers.maxconv'));
  }
  /**
   * Añade un postback a la cola de procesamiento.
   *
   * @param array $validatedData Los datos validados del postback
   * @return JsonResponse Resultado de la operación con el postback creado o error
   */
  public function queueForProcessing(array $validatedData): JsonResponse
  {
    try {
      $postback = DB::transaction(function () use ($validatedData) {
        // Crear postback con estado pending
        $postback = Postback::create([
          'offer_id' => $validatedData['offer_id'],
          'click_id' => $validatedData['clid'],
          'payout' => $validatedData['payout'] ?? null,
          'transaction_id' => $validatedData['txid'] ?? null,
          'currency' => $validatedData['currency'],
          'event' => $validatedData['event'],
          'vendor' => $validatedData['vendor'],
          'status' => Postback::statusPending(),
          'message' => 'Pending verification',
        ]);

        return $postback;
      });

      TailLogger::saveLog('Postback añadido a la cola de procesamiento', 'services/postback', 'info', [
        'postback_id' => $postback->id,
        'vendor' => $validatedData['vendor'],
        'offer_id' => $validatedData['offer_id'],
        'click_id' => $validatedData['clid']
      ]);

      //ProcessPostbackJob::dispatch($postback->id, $validated['clid']);
      return response()->json([
        'success' => true,
        'postback' => $postback,
        'message' => 'Postback received and queued for processing'
      ]);
    } catch (\Throwable $e) {
      // Verificar si es una QueryException con duplicado
      $errorMessage = $e->getMessage();
      if ($e instanceof QueryException) {
        $isDuplicateEntry = str_contains($errorMessage, 'duplicate key value violates unique constraint');
        if ($isDuplicateEntry) {
          return response()->json([
            'success' => false,
            'error' => 'duplicate',
            'message' => 'Duplicate transaction ID (transaction_id) for this vendor.'
          ], 422);
        }
      }

      TailLogger::saveLog('Error al añadir postback a la cola', 'services/postback', 'error', [
        'vendor' => $validatedData['vendor'] ?? 'unknown',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'error' => 'general',
        'message' => 'Error processing postback',
      ], 500);
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



/*
/*
  /**
   * Reconcilia los payouts de un día específico desde Natural Intelligence.
   *
   * @param string $date La fecha en formato Y-m-d.
   * @return array Un resumen de las operaciones.
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
        $offers = $this->getOffers();
        $offer = $offers->firstWhere('name', $conversion['pub_param_2']) ?? null;
        if (!$offer) {
          TailLogger::saveLog('PostbackService: No se encontró información de oferta para el click ID', 'postback-reconciliation', 'warning', [
            'click_id' => $clickId,
            'conversion' => $conversion
          ]);
          continue;
        }


        // Crear la instancia sin guardar aún
        $postback = new Postback([
          'click_id' => $clickId,
          'offer_id' => $offer['offer_id'],
          'event' => $offer['name'],
          'payout' => $conversion['payout'] ?? 0.0,
          'currency' => 'USD',
          'vendor' => PostbackVendor::NI->value(), // Asumimos 'ni' ya que usamos NaturalIntelligenceService
          'status' => Postback::statusPending(), // Marcar como pendiente
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
    if ($postback->status === Postback::statusProcessed()) {
      throw new PostbackServiceException("Postback already marked as processed", [
        'postback_id' => $postbackId,
        'exists' => $postback ? 'sí' : 'no',
        'status' => $postback?->status ?? 'N/A'
      ]);
    }
    // Verificar si no está fallido
    if ($postback->status === Postback::statusFailed()) {
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
  */
