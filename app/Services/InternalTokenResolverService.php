<?php

namespace App\Services;

use App\Models\Field;
use App\Models\Lead;
use App\Models\TrafficLog;
use Illuminate\Support\Facades\Cache;

class InternalTokenResolverService
{
  /**
   * Columnas de TrafficLog expuestas como tokens internos.
   *
   * @var string[]
   */
  private const TRAFFIC_LOG_TOKENS = [
    'ip_address',
    'user_agent',
    'device_type',
    'browser',
    'os',
    'referrer',
    'host',
    'path_visited',
    's1',
    's2',
    's3',
    's4',
    's10',
    'utm_source',
    'utm_medium',
    'campaign_code',
    'utm_campaign_id',
    'utm_campaign_name',
    'utm_term',
    'utm_content',
    'click_id',
    'platform',
    'channel',
    'country_code',
    'state',
    'city',
    'postal_code',
  ];

  /**
   * Retorna los tokens disponibles agrupados por origen.
   * Cachea el resultado por 24 horas (los Fields no cambian frecuentemente).
   *
   * @return array{fields: array<int, array{name: string, label: string, group: string}>, traffic: array<int, array{name: string, label: string, group: string}>}
   */
  public function getAvailableTokens(): array
  {
    return Cache::remember('internal_postback_tokens', now()->addDay(), function () {
      $fields = Field::query()
        ->orderBy('name')
        ->get(['id', 'name', 'label'])
        ->map(fn(Field $f) => [
          'id' => $f->id,
          'name' => $f->name,
          'label' => $f->label ?? $f->name,
          'group' => 'Lead Fields',
        ])
        ->values()
        ->all();

      $traffic = collect(self::TRAFFIC_LOG_TOKENS)
        ->map(fn(string $col, int $i) => [
          'id' => 10000 + $i,
          'name' => "traffic.{$col}",
          'label' => str_replace('_', ' ', ucfirst($col)),
          'group' => 'Traffic Log',
        ])
        ->values()
        ->all();

      return [
        'fields' => $fields,
        'traffic' => $traffic,
      ];
    });
  }

  /**
   * Retorna array flat de todos los tokens (para el combobox).
   *
   * @return array<int, array{id: int, name: string, label: string, group: string}>
   */
  public function getTokenList(): array
  {
    $tokens = $this->getAvailableTokens();

    return array_merge($tokens['fields'], $tokens['traffic']);
  }

  /**
   * Resuelve valores de todos los tokens a partir de un fingerprint.
   *
   * @return array<string, string|null>
   */
  public function resolveFromFingerprint(string $fingerprint): array
  {
    $values = [];

    $lead = Lead::getLeadWithResponses($fingerprint);

    if ($lead) {
      foreach ($lead->leadFieldResponses as $response) {
        if ($response->field) {
          $values[$response->field->name] = $response->value;
        }
      }
    }

    $trafficLog = TrafficLog::query()
      ->where('fingerprint', $fingerprint)
      ->latest()
      ->first();

    if ($trafficLog) {
      foreach (self::TRAFFIC_LOG_TOKENS as $col) {
        $values["traffic.{$col}"] = (string) ($trafficLog->{$col} ?? '');
      }
    }

    return $values;
  }
}
