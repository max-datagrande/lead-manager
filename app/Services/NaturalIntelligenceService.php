<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Maxidev\Logger\TailLogger;
use App\Models\PostbackApiRequests;
use App\Models\Postback;
use App\Libraries\NaturalIntelligence;


class NaturalIntelligenceService
{

  private int $postbackId;
  private array $report;
  private array $relevantFields = ['data_type', 'date', 'source_join', 'device', 'pub_param_1', 'pub_param_2', 'external_campaign_id', 'external_traffic_source', 'clickouts', 'leads', 'payout', 'sales', 'visits', 'bridge_visits', 'clicking_users', 'date_time'];

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
  public function getRecentConversionsReport(Postback $postback): array
  {
    if (empty($postback)) { // Check if postback is empty
      throw new NaturalIntelligenceServiceException('Invalid postback found in postback - Required postback object, found: null');
    }
    if ($postback->vendor !== 'ni') { // Check if postback vendor is ni
      throw new NaturalIntelligenceServiceException('Invalid vendor found in postback - Required vendor "ni", found: ' . $postback->vendor);
    }
    if ($postback->id === null) { // Check if postback ID is null
      throw new NaturalIntelligenceServiceException('Invalid postback ID found in postback - Required ID, found: ' . $postback->id);
    }
    //Set postback ID as a variable
    $this->postbackId = $postback->id;
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
      $report = $this->ni->getReport($payload);

      $responseTime = (int) ((microtime(true) - $startTime) * 1000);

      // Obtener la respuesta HTTP de la librería para logging
      $response = $this->ni->getLastResponse();

      TailLogger::saveLog('NI Service: Reporte obtenido exitosamente', 'api/ni', 'info', [
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'response_time_ms' => $responseTime
      ]);

      $this->report = $report;
      return [
        'success' => true,
        'data' => $report
      ];
    } catch (\App\Libraries\NaturalIntelligenceException $e) {
      // Manejar excepciones de la librería NaturalIntelligence
      if (!isset($responseTime)) {
        $responseTime = (int) ((microtime(true) - $startTime) * 1000);
      }

      // Obtener respuesta y payload de la librería
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

      TailLogger::saveLog('NI Service: Error de librería al obtener reporte', 'api/ni', 'error', [
        'error' => $e->getMessage(),
        'status_code' => $response ? $response->status() : null,
        'response_time_ms' => $responseTime,
        'trace' => $e->getTraceAsString(),
      ]);

      throw new NaturalIntelligenceServiceException('Error getting report: ' . $e->getMessage(), 0, $e);
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

      TailLogger::saveLog('NI Service: Excepción general al obtener reporte', 'api/ni', 'error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'response_time_ms' => $responseTime
      ]);

      throw new NaturalIntelligenceServiceException('Unexpected error getting report: ' . $e->getMessage(), 0, $e);
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
  public function getPayoutForClickId(string $clickId, Postback $postback): ?float
  {
    try {
      $reportResult = $this->getRecentConversionsReport($postback);
      if (!$reportResult['success']) {
        //Set the status to failed
        $postback->markAsFailed();
      }
      $conversions = $reportResult['data'] ?? [];
      if (empty($conversions)) {
      }
      $conversions = $reportResult['data'] ?? [];

      if (empty($conversions)) {
        TailLogger::saveLog('NI Service: No hay conversiones en el reporte', 'api/ni', 'warning', [
          'clickId' => $clickId
        ]);
        return null;
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
        return null;
      }

      $payout = $conversion['payout'] ?? null;

      if ($payout === null) {
        TailLogger::saveLog('NI Service: Payout no disponible para click ID', 'api/ni', 'warning', [
          'clickId' => $clickId,
          'conversion' => $conversion
        ]);
        return null;
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
      return null;
    } catch (\Exception $e) {
      TailLogger::saveLog('NI Service: Error inesperado al buscar payout para click ID', 'api/ni', 'error', [
        'clickId' => $clickId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
      return null;
    }
  }

  /**
   * Retorna el reporte buscado por clickid (método legacy)
   */
  public function getReportByClickId(string $clickId): array
  {
    if (!$this->report) {
      throw new NaturalIntelligenceServiceException('Report not found');
    }
    $report = collect($this->report);
    if (count($report) === 0) {
      throw new NaturalIntelligenceServiceException('Report empty');
    }
    $clickIdReport = $report->first(function ($report) use ($clickId) {
      return $report['pub_param_1'] === $clickId;
    }, null);
    if (!$clickIdReport) {
      throw new NaturalIntelligenceServiceException('Report not found with clickid: ' . $clickId);
    }
    return $clickIdReport;
  }

  /**
   * Obtiene el header de autenticación desde la librería NaturalIntelligence
   */
  private function getAuthHeader(): array
  {
    $token = Cache::get('ni_auth_token');
    if (!$token) {
      // Si no hay token, forzar renovación a través de la librería
      $this->ni->login();
      $token = Cache::get('ni_auth_token');
    }
    return ['Authorization' => (string) $token];
  }

  /**
   * Obtiene el payout de un reporte (método legacy)
   */
  public function getReportPayout(string $clickId): ?string
  {
    try {
      $report = $this->getReportByClickId($clickId);
      return $report['payout'];
    } catch (NaturalIntelligenceServiceException $e) {
      TailLogger::saveLog('NI Service: Excepción al obtener el payout', 'api/ni', 'error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
      return null;
    } catch (\Exception $e) {
      TailLogger::saveLog('NI Service: Error inesperado al obtener el payout', 'api/ni', 'error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
      return null;
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
