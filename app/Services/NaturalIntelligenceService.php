<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Maxidev\Logger\TailLogger;
use App\Models\PostbackApiRequests;
use App\Models\Postback;
use App\Libraries\NaturalIntelligence;
use App\Libraries\NaturalIntelligenceException;

class NaturalIntelligenceService
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
   * Obtiene reportes de conversiones de los últimos 3 días
   */
  public function getRecentConversionsReport(): array
  {
    $fromDate = now()->subDays(3)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');
    return $this->getConversionsReport($fromDate, $toDate);
  }

  /**
   * Obtiene reportes de conversiones desde NI API
   */
  public function getConversionsReport(string $fromDate, string $toDate): array
  {
    $startTime = microtime(true);
    try {
      $this->ni->login();
      // Preparar datos de la petición usando la librería
      $payload = $this->ni->buildPayload($fromDate, $toDate);
      // Obtener reporte usando la librería
      $this->report  = $this->ni->getReport($payload);
      $responseTime = (int) ((microtime(true) - $startTime) * 1000);

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

      // Intentar obtener respuesta y payload de la librería si están disponibles
      $response = $this->ni->getLastResponse();
      $payload = $this->ni->getLastPayload();

      PostbackApiRequests::create([
        'service' => PostbackApiRequests::SERVICE_NATURAL_INTELLIGENCE,
        'endpoint' => $this->getReportUrl(),
        'method' => 'POST',
        'request_data' => $payload,
        'status_code' => $response ? $response->status() : null,
        'error_message' => $e->getMessage(),
        'response_time_ms' => $responseTime,
        'related_type' => PostbackApiRequests::RELATED_TYPE_REPORT,
        'request_id' => uniqid('req_')
      ]);

      TailLogger::saveLog('NI Service: Error general al obtener reporte', 'api/ni', 'error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'response_time_ms' => $responseTime
      ]);

      throw new NaturalIntelligenceServiceException('Error getting report: ' . $e->getMessage(), 0, $e);
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
   * Busca un click específico en los reportes recientes y retorna el payout
   */
  public function getPayoutForClickId(string $clickId): ?float
  {
    try {
      $report = $this->getRecentConversionsReport();
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
        throw new PayoutNotFoundException('Click ID: ' . $clickId . ' not found in conversions');
      }

      $payout = $conversion['payout'] ?? null;
      if ($payout === null) {
        TailLogger::saveLog('NI Service: Payout no disponible para click ID', 'api/ni', 'warning', [
          'clickId' => $clickId,
          'conversion' => $conversion
        ]);
        throw new PayoutNotFoundException('Payout not found for click ID: ' . $clickId);
      }

      TailLogger::saveLog('NI Service: Payout encontrado para click ID', 'api/ni', 'info', [
        'clickId' => $clickId,
        'payout' => $payout
      ]);

      return (float) $payout;
    } catch (NaturalIntelligenceServiceException $e) {
      TailLogger::saveLog('NI Service: Error de servicio al buscar payout para click ID', 'api/ni', 'error', [
        'clickId' => $clickId,
        'error' => $e->getMessage()
      ]);
      throw $e;
    } catch (\Exception $e) {
      if ($e instanceof NaturalIntelligenceServiceException) {
        throw $e;
      }
      if ($e instanceof NaturalIntelligenceException) {
        throw new NaturalIntelligenceServiceException('Error getting payout: ' . $e->getMessage(), 0, $e);
      }

      TailLogger::saveLog('NI Service: Error inesperado al buscar payout para click ID', 'api/ni', 'error', [
        'clickId' => $clickId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
      throw new NaturalIntelligenceServiceException('Unexpected error getting payout: ' . $e->getMessage(), 0, $e);
    }
  }
}

class NaturalIntelligenceServiceException extends \Exception
{
  public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }
}

class PayoutNotFoundException extends \Exception
{
  public function __construct(string $clid)
  {
    parent::__construct("Payout not found for CLID: {$clid}");
  }
}
