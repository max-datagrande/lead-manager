<?php

namespace App\Interfaces\Alerts;

interface AlertChannelDriverInterface
{
  /**
   * Send an alert message through this channel driver.
   */
  public function send(string $webhookUrl, string $message, array $context = []): bool;

  /**
   * The unique identifier for this driver type (e.g. 'slack', 'twilio').
   */
  public static function typeName(): string;

  /**
   * The human-readable label for this driver type (e.g. 'Slack', 'Twilio').
   */
  public static function typeLabel(): string;
}
