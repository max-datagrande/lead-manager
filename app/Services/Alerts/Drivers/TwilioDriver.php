<?php

namespace App\Services\Alerts\Drivers;

use App\Interfaces\Alerts\AlertChannelDriverInterface;

class TwilioDriver implements AlertChannelDriverInterface
{
  public function send(string $webhookUrl, string $message, array $context = []): bool
  {
    // TODO: Implement Twilio integration
    return false;
  }

  public static function typeName(): string
  {
    return 'twilio';
  }

  public static function typeLabel(): string
  {
    return 'Twilio';
  }
}
