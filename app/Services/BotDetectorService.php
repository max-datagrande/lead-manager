<?php

namespace App\Services;

use Illuminate\Http\Request;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use DeviceDetector\DeviceDetector;
/* use Maxidev\Logger\TailLogger; */

class BotDetectorService
{
  protected ?string $botName = null;
  protected ?string $botType = null;
  protected ?string $botMessage = null;
  protected ?array $botInfo = null;
  protected bool $isBot = false;
  protected string $userAgent;

  public function __construct(protected Request $request) {}

  public function setUserAgent(string $userAgent): void
  {
    $this->userAgent = $userAgent;
  }
  public function detectBot(string $userAgent): bool
  {
    $this->setUserAgent($userAgent);
    return $this->detect();
  }
  /**
   * Detecta si el request es de un bot usando DeviceDetector y CrawlerDetect
   */
  public function detect(): bool
  {
    if (app()->environment('local')) {
      return false;
    }
    if ($this->isCrawlerBot()) {
      $this->isBot = true;
      return true;
    }
    $headerCheck = $this->checkHeaders();
    if ($headerCheck['is_bot']) {
      $this->botName = 'HEADER_' . $headerCheck['missing_type'];
      $this->botType = $headerCheck['missing_type'];
      $this->botMessage = "Missing header: {$headerCheck['missing_value']}";
      $this->isBot = true;
      return true;
    }
    return false;
  }

  /**
   * Detección por DeviceDetector y CrawlerDetect
   */
  public function isCrawlerBot(): bool
  {
    $userAgent = $this->userAgent ?? $this->request->userAgent() ?? null;
    if (empty($userAgent)) { //Is bot because of missing user agent
      $this->isBot = true;
      $this->botName = 'MISSING_USER_AGENT';
      $this->botType = 'Crawler';
      $this->botMessage = "Missing user agent";
      return true;
    }
    //Device detector
    $dd = new DeviceDetector($userAgent);
    $dd->discardBotInformation(false);
    $dd->skipBotDetection(false);
    $dd->parse();
    if ($dd->isBot()) {
      $botInfo = $dd->getBot();
      $this->botInfo = $botInfo;
      $this->botName = $botInfo['name'] ?? $this->getBotCrawlerName($userAgent);
      $this->botType = $botInfo['category'] ?? 'Crawler';
      $this->botMessage = "Detected by DeviceDetector: {$this->botName}";
      return true;
    }
    //Crawler detect
    $crawlerDetect = new CrawlerDetect();
    if ($crawlerDetect->isCrawler($userAgent)) {
      $this->botName = $crawlerDetect->getMatches() ?? 'Unknown';
      $this->botType = 'Crawler';
      $this->botMessage = "Detected by CrawlerDetect: {$this->botName}";
      return true;
    }
    return false;
  }

  private function getBotCrawlerName(): string
  {
    $crawlerDetect = new CrawlerDetect();
    $botName = $crawlerDetect->getMatches();
    return $botName ?? 'UNKNOWN';
  }

  /**
   * Verificación avanzada de headers
   */
  private function checkHeaders(): array
  {
    $result = [
      'is_bot' => false,
      'missing_type' => null,
      'missing_value' => null,
    ];
    $headers = $this->request->headers->all();
    // Verificar headers básicos
    $basicCheck = [
      'accept-language' => $headers['accept-language'][0] ?? null,
      'accept' => $headers['accept'][0] ?? null,
    ];
    foreach ($basicCheck as $header => $value) {
      if (!$value) {
        $result['is_bot'] = true;
        $result['missing_type'] = 'MISSING_HEADER';
        $result['missing_value'] = $header;
        $this->botMessage = "Missing header: $header";
        return $result;
      }
    }
    return $result;
  }

  /**
   * Determina si el bot es para dispositivo móvil
   */
  public function isMobileBot(?string $userAgent = null): bool
  {
    $userAgent = $userAgent ?? $this->request->userAgent();
    return stripos($userAgent, 'mobile') !== false;
  }

  /**
   * Guarda la información del bot en la base de datos (stub)
   */
  public function saveToDatabase(): int|false
  {
    // Implementa según tu modelo de Bot
    return false;
  }

  /**
   * Método handle vacío para futura lógica
   */
  public function handle(): void
  {
    // ...
  }

  // Getters para bot info
  public function getBotName(): ?string
  {
    return $this->botName;
  }
  public function getBotType(): ?string
  {
    return $this->botType;
  }
  public function getBotMessage(): ?string
  {
    return $this->botMessage;
  }
  public function getBotInfo(): ?array
  {
    return $this->botInfo;
  }
}
