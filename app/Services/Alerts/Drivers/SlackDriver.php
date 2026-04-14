<?php

namespace App\Services\Alerts\Drivers;

use App\Interfaces\Alerts\AlertChannelDriverInterface;
use App\Libraries\Slack;

class SlackDriver implements AlertChannelDriverInterface
{
  public function send(string $webhookUrl, string $message, array $context = []): bool
  {
    $slack = new Slack($webhookUrl);

    if (!empty($context)) {
      return $slack->sendErrorNotification(title: $context['title'] ?? 'Alert', message: $message, fields: $context['fields'] ?? []);
    }

    return $slack->sendMessage($message);
  }

  public static function typeName(): string
  {
    return 'slack';
  }

  public static function typeLabel(): string
  {
    return 'Slack';
  }
}
