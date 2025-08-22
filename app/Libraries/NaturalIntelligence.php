<?php

namespace App\Libraries;

use App\Models\Postback;
use App\Services\NaturalIntelligenceService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Maxidev\Logger\TailLogger;

class NaturalIntelligence
{
  public readonly string $reportUrl;
  public readonly string $loginUrl;
  public readonly string $username;
  public readonly string $password;
  public static ?string $token = null;
  private array $relevantFields = ['data_type', 'date', 'source_join', 'device', 'pub_param_1', 'pub_param_2', 'external_campaign_id', 'external_traffic_source', 'clickouts', 'leads', 'payout', 'sales', 'visits', 'bridge_visits', 'clicking_users', 'date_time'];

  public function __construct()
  {
    $this->loginUrl = config('services.natural_intelligence.login_url');
    $this->reportUrl = config('services.natural_intelligence.report_url');
    $this->username = config('services.natural_intelligence.username');
    $this->password = config('services.natural_intelligence.password');
  }

  public function login(): void
  {
    try {
      self::$token = $this->getAuthToken();
    } catch (NaturalIntelligenceException $th) {
      throw new NaturalIntelligenceException('Failed accessing Natural Intelligence API: ' . $th->getMessage(), 0, $th);
    } catch (\Throwable $th) {
      throw new NaturalIntelligenceException('Unexpected error accessing Natural Intelligence API: ' . $th->getMessage(), 0, $th);
    }
  }
  /**
   * Obtiene token de autenticación de NI (con cache de 23 horas)
   */
  private function getAuthToken(): ?string
  {
    return Cache::remember('ni_auth_token', 23 * 60 * 60, function () {
      $this->Logging('Solicitando nuevo token de autenticación');
      $response = Http::timeout(30)
        ->post($this->loginUrl, [
          'username' => $this->username,
          'password' => $this->password,
        ]);
      $this->Logging('Api Response', 'info', [
        'status' => $response->status(),
        'response' => $response->body()
      ]);

      if (!$response->successful()) {
        $this->Logging('Error obtaining token - Response unsuccessful');
        throw new NaturalIntelligenceException('Error obtaining token - Response unsuccessful');
      }
      $token = $response->body();
      if (!$token) {
        $this->Logging('Error obtaining token - Empty token');
        throw new NaturalIntelligenceException('Error obtaining token - Empty token');
      }
      $this->Logging('Token obtenido exitosamente');
      return $token;
    });
  }

  public function getReport(?string $fromDate = null, ?string $toDate = null): array
  {
    $requestData = [
      'FromDate' => $fromDate ?? now()->subDays(3)->format('Y-m-d'),
      'ToDate' => $toDate ?? now()->format('Y-m-d'),
      'ReportFormat' => "json",
      'ReportType' => 'Summary',
      'DataType' => 'conversions'
    ];
    $this->Logging('Solicitando reporte de conversiones', 'info', [
      'request_data' => $requestData,
    ]);
    try {
      $response = Http::timeout(60)
        ->withHeaders(['Authorization' => self::$token])
        ->post($this->reportUrl, $requestData);
      $this->Logging('Status Response: ' . $response->status());
      return $this->handleReportResponse($response);
    } catch (NaturalIntelligenceException $th) {
      throw new NaturalIntelligenceException('Error obtaining report - ' . $th->getMessage(), 0, $th);
    } catch (\Throwable $th) {
      $this->Logging('Unexpected error obtaining report', 'error', [
        'error' => $th->getMessage(),
        'trace' => $th->getTraceAsString()
      ]);
      throw new NaturalIntelligenceException('Unexpected error obtaining report - ' . $th->getMessage(), 0, $th);
    }
  }
  private function handleReportResponse($response): array
  {
    if (!$response->successful()) {
      $this->Logging('Error obtaining report - Response unsuccessful', 'error');
      throw new NaturalIntelligenceException('Response unsuccessful');
    }
    $report = $response->json();
    if (!$report) {
      $this->Logging('Error obtaining report - Invalid json response', 'error', [
        'response' => $response->body(),
      ]);
      throw new NaturalIntelligenceException('Invalid json response');
    }
    $report = $this->filterResponse($report);
    $this->Logging('Report obtained successfully', 'info', [
      'report' => $report,
    ]);
    return $report;
  }
  public function filterResponse(?array $responseData): ?array
  {
    if (!$responseData || !is_array($responseData)) {
      return $responseData;
    }
    $data = collect($responseData)->filter(function ($item, $key) {
      return in_array($key, $this->relevantFields);
    });
    return $data->toArray();
  }
  private function Logging(string $message, ?string $type = 'info', ?array $data = []): void
  {
    TailLogger::saveLog("NI Library: $message", 'api/ni',  $type, $data);
  }
}

class NaturalIntelligenceException extends \Exception {}
