<?php

namespace App\Services\Alerts;

use App\Interfaces\Alerts\AlertChannelDriverInterface;
use App\Services\Alerts\Drivers\SlackDriver;
use App\Services\Alerts\Drivers\TwilioDriver;
use InvalidArgumentException;

class AlertChannelResolver
{
  /**
   * @var array<string, class-string<AlertChannelDriverInterface>>
   */
  protected array $driverMap = [
    'slack' => SlackDriver::class,
    'twilio' => TwilioDriver::class,
  ];

  public function make(string $type): AlertChannelDriverInterface
  {
    $driverClass = $this->driverMap[$type] ?? null;

    if (!$driverClass) {
      throw new InvalidArgumentException("No alert channel driver registered for '{$type}'.");
    }

    return app($driverClass);
  }

  /**
   * @return array<int, array{value: string, label: string}>
   */
  public function availableTypes(): array
  {
    return array_map(
      fn(string $driverClass) => [
        'value' => $driverClass::typeName(),
        'label' => $driverClass::typeLabel(),
      ],
      array_values($this->driverMap),
    );
  }

  public function isRegistered(string $type): bool
  {
    return array_key_exists($type, $this->driverMap);
  }

  /**
   * @return string[]
   */
  public function registeredTypeNames(): array
  {
    return array_keys($this->driverMap);
  }
}
