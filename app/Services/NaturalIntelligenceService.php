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
   * Retorna el reporte buscado por clickid
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
    if (!$report) {
      throw new NaturalIntelligenceServiceException('Report not found with clickid: ' . $clickId);
    }
    return $clickIdReport;
  }
}
