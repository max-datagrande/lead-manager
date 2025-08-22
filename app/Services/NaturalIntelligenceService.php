<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Maxidev\Logger\TailLogger;

class NaturalIntelligenceServiceException extends \Exception
{
  public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }
}
/**
 * Servicio para manejar postbacks y reportes con Natural Intelligence
 */
class NaturalIntelligenceService
{
  private string $loginUrl;
  private string $reportUrl;
  private string $username;
  private string $password;
  private array $report;

  public function __construct()
  {
    $this->loginUrl = 'https://partner-login.naturalint.com/token';
    $this->reportUrl = 'https://partner-api.naturalint.com/publisherhubservice/get-report';
    $this->username = config('services.natural_intelligence.username');
    $this->password = config('services.natural_intelligence.password');
  }

  /**
   * Obtiene token de autenticación de NI (con cache de 23 horas)
   */
  private function getAuthToken(): ?string
  {
    return Cache::remember('ni_auth_token', 23 * 60 * 60, function () {
      TailLogger::saveLog('NI Service: Solicitando nuevo token de autenticación', 'api/ni', 'info', [
        'username' => $this->username
      ]);
      $response = Http::timeout(30)
        ->post($this->loginUrl, [
          'username' => $this->username,
          'password' => $this->password,
        ]);

      if ($response->successful()) {
        $token = $response->body();
        TailLogger::saveLog('NI Service: Token obtenido exitosamente', 'api/ni', 'info', [
          'username' => $this->username
        ]);
        return $token;
      }

      TailLogger::saveLog('NI Service: Error al obtener token', 'api/ni', 'error', [
        'status' => $response->status(),
        'response' => $response->body()
      ]);
      throw new NaturalIntelligenceServiceException('Error obtaining token');
    });
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
    try {
      $token = $this->getAuthToken();
      TailLogger::saveLog('NI Service: Solicitando reporte de conversiones', 'api/ni', 'info', [
        'from_date' => $fromDate,
        'to_date' => $toDate,
      ]);

      $response = Http::timeout(60)
        ->withHeaders(['Authorization' => $token])
        ->post($this->reportUrl, [
          'FromDate' => $fromDate,
          'ToDate' => $toDate,
          'ReportFormat' => "json",
          'ReportType' => 'Summary',
          'DataType' => 'conversions'
        ]);

      if ($response->successful()) {
        TailLogger::saveLog('NI Service: Reporte obtenido exitosamente', 'api/ni', 'info', [
          'from_date' => $fromDate,
          'to_date' => $toDate
        ]);
        $report = $response->json();
        $this->report = $report;
        return [
          'success' => true,
          'data' => $report
        ];
      }
      TailLogger::saveLog('NI Service: Error al obtener reporte', 'api/ni', 'error', [
        'status' => $response->status(),
        'response' => $response->body()
      ]);
      throw new NaturalIntelligenceServiceException('Error getting report');
    } catch (NaturalIntelligenceServiceException $e) {
      TailLogger::saveLog('NI Service: Excepción al obtener reporte', 'api/ni', 'error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);
      return [
        'success' => false,
        'message' => 'Failed to get report',
        'error' => $e->getMessage(),
      ];
    } catch (\Exception $e) {
      Log::error('NI Service: Excepción al obtener reporte', [
        'error' => $e->getMessage()
      ]);
      return [
        'success' => false,
        'message' => 'Unexpected error while getting report',
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Busca un click específico en los reportes recientes y retorna el payout
   */
  public function getPayoutForClickId(string $clickId): ?float
  {
    try {
      $reportResult = $this->getRecentConversionsReport();
      
      if (!$reportResult['success']) {
        TailLogger::saveLog('NI Service: Error al obtener reporte reciente', 'api/ni', 'error', [
          'clickId' => $clickId,
          'error' => $reportResult['message'] ?? 'Unknown error'
        ]);
        return null;
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
      
    } catch (\Exception $e) {
      TailLogger::saveLog('NI Service: Error al buscar payout para click ID', 'api/ni', 'error', [
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
