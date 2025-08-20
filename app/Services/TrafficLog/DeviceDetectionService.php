<?php

namespace App\Services\TrafficLog;

use Jenssegers\Agent\Agent;

/**
 * Servicio de detección de dispositivos
 *
 * Combina los helpers existentes del proyecto con Jenssegers\Agent\Agent
 * para proporcionar información detallada sobre el dispositivo del usuario
 */
class DeviceDetectionService
{

  public function __construct(protected Agent $agent) {}

  /**
   * Detecta información del dispositivo basada en el user agent
   *
   * @param string $userAgent
   * @return array
   */
  public function detectDevice(string $userAgent): array
  {
    // Configurar el user agent en el agente
    $this->agent->setUserAgent($userAgent);
    $browser    = $this->agent->browser();
    $os         = $this->agent->platform();
    $deviceType = $this->agent->isMobile() ? 'mobile' : 'desktop';
    $result = compact('deviceType', 'browser', 'os');
    return $result;
  }

  /**
   * Obtiene información adicional del dispositivo
   *
   * @return array
   */
  public function getAdditionalInfo(): array
  {
    return [
      'device_name' => $this->agent->device(),
      'browser_version' => $this->agent->version($this->agent->browser()),
      'platform_version' => $this->agent->version($this->agent->platform()),
      'is_robot' => $this->agent->isRobot(),
      'robot_name' => $this->agent->robot(),
      'languages' => $this->agent->languages(),
    ];
  }

  /**
   * Verifica si el dispositivo es móvil
   */
  public function isMobile(): bool
  {
    return $this->agent->isMobile();
  }

  /**
   * Verifica si el dispositivo es tablet
   */
  public function isTablet(): bool
  {
    return $this->agent->isTablet();
  }

  /**
   * Verifica si el dispositivo es desktop
   */
  public function isDesktop(): bool
  {
    return $this->agent->isDesktop();
  }

  /**
   * Verifica si es un robot/bot
   */
  public function isRobot(): bool
  {
    return $this->agent->isRobot();
  }
}
