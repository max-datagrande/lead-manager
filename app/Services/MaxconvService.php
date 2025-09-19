<?php

namespace App\Services;

use App\Models\Postback;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Maxidev\Logger\TailLogger;

class MaxconvService
{
  /**
   * Configuración de ofertas desde config/offers.php
   */
  protected array $offers;

  public function __construct()
  {
    $this->offers = config('offers.maxconv', []);
  }

  /**
   * Obtiene la configuración de una oferta específica
   */
  public function getOffer(string $offerId): ?array
  {
    return collect($this->offers)->firstWhere('offer_id', $offerId);
  }

  /**
   * Procesa los placeholders dinámicos de una URL
   *
   * @param string $url URL con placeholders
   * @param array $data Datos para reemplazar placeholders
   * @return string URL procesada
   */
  public function processPlaceholders(string $url, array $data): string
  {
    // Buscar todos los placeholders en formato {placeholder}
    preg_match_all('/\{([^}]+)\}/', $url, $matches);

    if (empty($matches[1])) {
      return $url;
    }

    $processedUrl = $url;

    foreach ($matches[1] as $placeholder) {
      $value = $data[$placeholder] ?? '';
      $processedUrl = str_replace('{' . $placeholder . '}', $value, $processedUrl);
    }

    return $processedUrl;
  }

  /**
   * Construye los datos de postback para una oferta específica
   * Solo incluye los parámetros que corresponden a los placeholders de la URL
   *
   * @param Postback $postback Modelo de postback
   * @return array Datos estructurados para el postback
   */
  public function buildPostbackData(Postback $postback): array
  {
    $offer = $this->getOffer($postback->offer_id);

    if (!$offer) {
      Log::warning("Oferta no encontrada: {$postback->offer_id}");
      return [];
    }

    // Solo los parámetros que corresponden a los placeholders de la URL de postback
    $postbackData = [
      'clid' => $postback->click_id,        // {click_id} -> clid en la URL
      'payout' => $postback->payout,        // {payout}
      'txid' => $postback->transaction_id,  // {transaction_id} -> txid en la URL
      'currency' => 'USD',                  // {currency}
      'event' => $postback->event ?? 'conversion', // {event}
    ];

    return $postbackData;
  }

  /**
   * Construye la URL base de postback sin parámetros
   * Los parámetros se enviarán por separado en buildPostbackData()
   *
   * @param Postback $postback Modelo de postback
   * @return string|null URL base de postback
   */
  public function buildPostbackUrl(Postback $postback): ?string
  {
    $offer = $this->getOffer($postback->offer_id);
    if (!$offer || empty($offer['postback_url'])) {
      Log::warning("URL de postback no encontrada para oferta: {$postback->offer_id}");
      return null;
    }

    // Extraer solo la URL base sin parámetros
    $url = $offer['postback_url'];
    $parsedUrl = parse_url($url);

    if (!$parsedUrl) {
      return null;
    }

    $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
    if (isset($parsedUrl['port'])) {
      $baseUrl .= ':' . $parsedUrl['port'];
    }
    if (isset($parsedUrl['path'])) {
      $baseUrl .= $parsedUrl['path'];
    }

    return $baseUrl;
  }

  /**
   * Obtiene la URL de la oferta procesada con placeholders
   *
   * @param string $offerId ID de la oferta
   * @param array $data Datos para los placeholders
   * @return string|null URL procesada
   */
  public function buildOfferUrl(string $offerId, array $data): ?string
  {
    $offer = $this->getOffer($offerId);

    if (!$offer || empty($offer['url'])) {
      Log::warning("URL de oferta no encontrada: {$offerId}");
      return null;
    }

    return $this->processPlaceholders($offer['url'], $data);
  }

  /**
   * Valida que todos los placeholders requeridos estén presentes
   *
   * @param string $url URL con placeholders
   * @param array $data Datos disponibles
   * @return array Placeholders faltantes
   */
  public function validatePlaceholders(string $url, array $data): array
  {
    preg_match_all('/\{([^}]+)\}/', $url, $matches);

    if (empty($matches[1])) {
      return [];
    }

    $missingPlaceholders = [];

    foreach ($matches[1] as $placeholder) {
      if (!isset($data[$placeholder]) || $data[$placeholder] === '') {
        $missingPlaceholders[] = $placeholder;
      }
    }

    return $missingPlaceholders;
  }

  /**
   * Obtiene todas las ofertas disponibles
   *
   * @return array Lista de ofertas
   */
  public function getAllOffers(): array
  {
    return $this->offers;
  }

  /**
   * Verifica si una oferta existe
   *
   * @param string $offerId ID de la oferta
   * @return bool
   */
  public function offerExists(string $offerId): bool
  {
    return !is_null($this->getOffer($offerId));
  }
  /**
   * Procesar un postback completo (método principal para PostbackService)
   * Retorna un array con url, data y response
   */
  public function processPostback(Postback $postback): array
  {
    // Obtener la configuración de la oferta
    $offer = $this->getOffer($postback->offer_id);
    if (!$offer) {
      throw new \Exception("Offer not found: {$postback->offer_id}");
    }

    // Construir la URL de postback
    $postbackUrl = $this->buildPostbackUrl($postback);
    if (!$postbackUrl) {
      throw new \Exception("Could not build postback URL for offer: {$postback->offer_id}");
    }

    // Construir los datos del postback
    $postbackData = $this->buildPostbackData($postback);

    // Ejecutar el postback
    $response = $this->executePostbackRequest($postbackUrl, $postbackData);

    return [
      'url' => $postbackUrl,
      'data' => $postbackData,
      'response' => $response
    ];
  }

  /**
   * Ejecuta la petición HTTP del postback
   *
   * @param string $postbackUrl URL de postback
   * @param array $postbackData Datos para enviar
   * @return Response Respuesta HTTP
   */
  public function executePostbackRequest(string $postbackUrl, array $postbackData): Response
  {
    // Si es entorno local, simular respuesta exitosa
    if (app()->environment('local')) {
      return new Response(
        new Psr7Response(200, [], json_encode([
          'success' => true,
          'message' => 'Simulated response for local environment',
          'data' => $postbackData
        ]))
      );
    }
    $response = Http::timeout(30)->get($postbackUrl, $postbackData);
    //Logging to update taillogger after
    TailLogger::saveLog("Postback redirigido exitosamente", 'services/postback-redirect', 'success', [
      'postbackData' => $postbackData,
      'postbackUrl' => $postbackUrl,
      'body' => $response->body(),
      'statusCode' => $response->status(),
    ]);
    return $response;
  }
}
