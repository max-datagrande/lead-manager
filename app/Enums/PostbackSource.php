<?php

namespace App\Enums;

enum PostbackSource: string
{
  case EXTERNAL_API = 'external_api';
  case OFFERWALL = 'offerwall';
  case PING_POST = 'ping_post';
  case WORKFLOW = 'workflow';
  case MANUAL = 'manual';
  case COMMAND = 'command';
  case SYSTEM = 'system';

  public function label(): string
  {
    return match ($this) {
      self::EXTERNAL_API => 'External API',
      self::OFFERWALL => 'Offerwall',
      self::PING_POST => 'Ping Post',
      self::WORKFLOW => 'Workflow',
      self::MANUAL => 'Manual',
      self::COMMAND => 'Command',
      self::SYSTEM => 'System',
    };
  }

  /**
   * @return array<int, array{value: string, label: string}>
   */
  public static function toArray(): array
  {
    return array_map(
      fn(self $source) => [
        'value' => $source->value,
        'label' => $source->label(),
      ],
      self::cases(),
    );
  }
}
