<?php

namespace App\Services;

use Maxidev\Logger\TailLogger;
use App\Models\PostbackApiRequests;
use App\Interfaces\Postback\VendorIntegrationInterface;
use App\Libraries\NaturalIntelligence;
use App\Libraries\NaturalIntelligenceException;

class NaturalIntelligenceService implements VendorIntegrationInterface
{

  private int $postbackId;
  private array $report;
  private array $relevantFields = ['data_type', 'date', 'source_join', 'device', 'pub_param_1', 'pub_param_2', 'external_campaign_id', 'external_traffic_source', 'clickouts', 'leads', 'payout', 'sales', 'visits', 'bridge_visits', 'clicking_users', 'date_time'];

  const NO_PAYOUT_RETURN_VALUE = 0;
  public function __construct(protected NaturalIntelligence $ni) {}

  public function setPostbackId(int $postbackId): void
  {
    $this->postbackId = $postbackId;
  }
  public function getReportUrl(): string
  {
    return $this->ni->reportUrl;
  }

  /**
   * Obtiene reportes de conversiones desde NI API
   *
   * @param string|null $fromDate Fecha de inicio (Y-m-d)
   * @param string|null $toDate Fecha de fin (Y-m-d)
   * @param string|null $relatedType Tipo de relación para el request
   * @return array{success: bool, data: array<int, array{data_type: string, device: string, pub_param_1: string, pub_param_2: string, external_campaign_id: string, external_traffic_source: string, clickouts: int, leads: int, payout: float, sales: int, visits: int, date_time: string}>}
   *
   * Estructura de respuesta:
   * - success: bool - Indica si la operación fue exitosa
   * - data: array - Lista de conversiones con la siguiente estructura:
   *   - data_type: string - Tipo de dato (ej: "payout")
   *   - device: string - Dispositivo (ej: "Mobile", "Desktop")
   *   - pub_param_1: string - Click ID único
   *   - pub_param_2: string - Nombre de la campaña/oferta
   *   - external_campaign_id: string - ID de campaña externa
   *   - external_traffic_source: string - Fuente de tráfico
   *   - clickouts: int - Número de clickouts
   *   - leads: int - Número de leads
   *   - payout: float - Payout en USD
   *   - sales: int - Número de ventas
   *   - visits: int - Número de visitas
   *   - date_time: string - Fecha y hora en formato ISO 8601
   */
  public function getConversionsReport(?string $fromDate, ?string $toDate, ?string $relatedType): array
  {
    if (!$toDate) {
      $toDate = now()->format('Y-m-d');
    }
    if (!$fromDate) {
      $fromDate = now()->subDays(3)->format('Y-m-d');
    }
    // Preparar datos de la petición usando la librería
    $startTime = microtime(true);
    $payload = $this->ni->buildPayload($fromDate, $toDate);

    // Registrar petición de reporte
    $apiRequest = PostbackApiRequests::create([
      'service' => PostbackApiRequests::SERVICE_NATURAL_INTELLIGENCE,
      'endpoint' => $this->ni->reportUrl,
      'method' => 'POST',
      'request_data' => $payload,
      'related_type' => $relatedType ?? PostbackApiRequests::RELATED_TYPE_REPORT,
      'postback_id' => $this->postbackId ?? null,
      'request_id' => uniqid('req_'),
    ]);

    return $this->executeReportRequest($apiRequest, $payload, $startTime, $fromDate, $toDate);
  }


  /**
   * Ejecuta la petición de reporte y maneja la respuesta
   */
  private function executeReportRequest(
    PostbackApiRequests $apiRequest,
    array $payload,
    float $startTime,
    string $fromDate,
    string $toDate
  ): array {

    try {
      $this->ni->login();
      // Obtener reporte usando la librería
      $this->report = $this->ni->getReport($payload);
      $responseTime = (int) ((microtime(true) - $startTime) * 1000);

      // Obtener información de la última petición
      $lastResponse = $this->ni->getLastResponse();

      // Actualizar el registro con la respuesta exitosa
      $apiRequest->updateWithResponse(
        $lastResponse ? $lastResponse->json() : $this->report,
        $lastResponse ? $lastResponse->status() : 200,
        null,
        $responseTime
      );

      TailLogger::saveLog('NI Service: Reporte obtenido exitosamente', 'api/ni', 'info', [
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'response_time_ms' => $responseTime
      ]);

      return [
        'success' => true,
        'data' => $this->report
      ];
    } catch (\Exception $e) {
      // Manejar excepciones generales
      if (!isset($responseTime)) {
        $responseTime = (int) ((microtime(true) - $startTime) * 1000);
      }

      // Obtener información de la última petición fallida
      $lastResponse = $this->ni->getLastResponse();

      // Actualizar el registro con el error
      $apiRequest->updateWithResponse(
        $lastResponse ? $lastResponse->json() : null,
        $lastResponse ? $lastResponse->status() : ($e->getCode() ?: 500),
        $e->getMessage(),
        $responseTime
      );

      TailLogger::saveLog('NI Service: Error general al obtener reporte', 'api/ni', 'error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'response_time_ms' => $responseTime
      ]);

      throw new NaturalIntelligenceServiceException('Error getting report: ' . $e->getMessage(), [
        'error' => $e->getMessage(),
        'response_time_ms' => $responseTime,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
      ], 0, $e);
    }
  }

  public function filterRelevantResponseData(?array $responseData): ?array
  {
    if (!$responseData || !is_array($responseData)) {
      return $responseData;
    }
    $data = collect($responseData)->filter(function ($item, $key) {
      return in_array($key, $this->relevantFields);
    });
    return $data->toArray();
  }

  /**
   * Busca un click específico en los reportes del vendor y retorna el payout.
   *
   * @param string $clickId
   * @param string|null $fromDate
   * @param string|null $toDate
   * @return float|null
   */
  public function getPayoutForClickId(string $clickId, ?string $fromDate, ?string $toDate): ?float
  {
    try {
      $report = $this->getConversionsReport($fromDate, $toDate, PostbackApiRequests::RELATED_TYPE_SEARCH_PAYOUT);
      if (!$report['success']) {
        TailLogger::saveLog('NI Service: Reporte no exitoso', 'api/ni', 'error', $report);
        throw new PayoutNotFoundException('Report no success');
      }
      $conversions = $report['data'] ?? [];
      if (empty($conversions)) {
        TailLogger::saveLog('NI Service: No hay conversiones en el reporte', 'api/ni', 'warning', [
          'clickId' => $clickId
        ]);
        throw new PayoutNotFoundException('No conversions found');
      }

      // Buscar la conversión por pub_param_1 (clickId)
      $conversion = collect($conversions)->first(function ($item) use ($clickId) {
        return isset($item['pub_param_1']) && $item['pub_param_1'] === $clickId;
      });

      if (!$conversion) {
        TailLogger::saveLog('NI Service: Click ID no encontrado en conversiones', 'api/ni', 'warning', [
          'clickId' => $clickId,
          'total_conversions' => count($conversions)
        ]);
        throw new PayoutNotFoundException('Click ID not found in payouts: ' . $clickId);
      }

      $payout = $conversion['payout'] ?? null;
      if ($payout === null) {
        TailLogger::saveLog('NI Service: Payout no disponible para click ID', 'api/ni', 'warning', [
          'clickId' => $clickId,
          'conversion' => $conversion
        ]);
        throw new PayoutNotFoundException('Click ID found but with no payout value: ' . $clickId);
      }

      TailLogger::saveLog('NI Service: Payout encontrado para click ID', 'api/ni', 'info', [
        'clickId' => $clickId,
        'payout' => $payout
      ]);

      return (float) $payout;
    } catch (PayoutNotFoundException $e) {
      // Si no se encuentra el payout, simplemente relanzamos la excepción
      throw $e;
    } catch (NaturalIntelligenceServiceException $e) {
      TailLogger::saveLog('NI Service: Error de servicio al buscar payout para click ID', 'api/ni', 'error', [
        'clickId' => $clickId,
        'error' => $e->getMessage()
      ]);
      throw $e;
    } catch (NaturalIntelligenceException $e) {
      throw new NaturalIntelligenceServiceException('Error getting payout: ' . $e->getMessage(), [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
      ], 0, $e);
    } catch (\Exception $e) {
      TailLogger::saveLog('NI Service: Error inesperado al buscar payout para click ID', 'api/ni', 'error', [
        'clickId' => $clickId,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
      throw new NaturalIntelligenceServiceException('Unexpected error getting payout: ' . $e->getMessage(), [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
      ], 0, $e);
    }
  }
}

class NaturalIntelligenceServiceException extends \Exception
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

class PayoutNotFoundException extends \Exception
{
  public function __construct(string $clickId)
  {
    parent::__construct("Payout not found: {$clickId}");
  }
}



/**
 * Obtiene reportes de conversiones para reconciliación diaria (sin postback_id)
 */
/* public function getReportForReconciliation(string $fromDate, string $toDate): array
{
  // Preparar datos de la petición usando la librería
  $startTime = microtime(true);
  $payload = $this->ni->buildPayload($fromDate, $toDate);

  // Registrar petición de reconciliación (sin postback_id)
  $apiRequest = PostbackApiRequests::create([
    'service' => PostbackApiRequests::SERVICE_NATURAL_INTELLIGENCE,
    'endpoint' => $this->ni->reportUrl,
    'method' => 'POST',
    'request_data' => $payload,
    'related_type' => PostbackApiRequests::RELATED_TYPE_RECONCILIATION,
    'postback_id' => null, // Explícitamente null para reconciliación
    'request_id' => uniqid('reconcile_'),
  ]);
  return $this->executeReportRequest($apiRequest, $payload, $startTime, $fromDate, $toDate);
} */
