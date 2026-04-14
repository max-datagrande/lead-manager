<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CacheRegistryService
{
  /** @var array<string, array{key: string, label: string, description: string, ttl: int|null, group: string, source: string, pattern?: bool}> */
  private array $entries = [];

  public function __construct()
  {
    $this->registerAll();
  }

  private function registerAll(): void
  {
    $this->register('visitors:hosts', [
      'label' => 'Visitor Hosts',
      'description' => 'Distinct host values from traffic_logs for visitor page filters.',
      'ttl' => 3600,
      'group' => 'Visitors',
      'source' => 'VisitorService',
    ]);

    $this->register('visitors:states', [
      'label' => 'Visitor States',
      'description' => 'Distinct state values from traffic_logs for visitor page filters.',
      'ttl' => 3600,
      'group' => 'Visitors',
      'source' => 'VisitorService',
    ]);

    $this->register('ow_conversion_hosts', [
      'label' => 'Offerwall Conversion Hosts',
      'description' => 'Distinct hosts from traffic_logs for offerwall conversion filters.',
      'ttl' => 600,
      'group' => 'Offerwall',
      'source' => 'OfferwallController',
    ]);

    $this->register('catalyst.manifest', [
      'label' => 'Catalyst CDN Manifest',
      'description' => 'Parsed manifest.json for the Catalyst JS engine.',
      'ttl' => null,
      'group' => 'System',
      'source' => 'CatalystController',
    ]);

    $this->register('internal_postback_tokens', [
      'label' => 'Internal Postback Tokens',
      'description' => 'Auto-discovered token list from fields and traffic log columns.',
      'ttl' => 86400,
      'group' => 'Postbacks',
      'source' => 'InternalTokenResolverService',
    ]);

    $this->register('vps_metrics', [
      'label' => 'VPS Metrics',
      'description' => 'Hostinger VPS CPU, RAM, and disk metrics.',
      'ttl' => 300,
      'group' => 'System',
      'source' => 'HostingerVpsService',
    ]);

    $this->register('ni_auth_token', [
      'label' => 'NI Auth Token',
      'description' => 'Natural Intelligence API authentication token.',
      'ttl' => 82800,
      'group' => 'Integrations',
      'source' => 'NaturalIntelligence',
    ]);

    $this->register('geolocation:ip:*', [
      'label' => 'Geolocation IP Cache',
      'description' => 'Cached IP geolocation lookups. Pattern-based, many keys.',
      'ttl' => 86400,
      'group' => 'Geolocation',
      'source' => 'GeolocationService',
      'pattern' => true,
    ]);

    $this->register('geolocation:zip:*', [
      'label' => 'Geolocation Zip Cache',
      'description' => 'Cached zipcode-to-city/state lookups. Pattern-based, many keys.',
      'ttl' => 2592000,
      'group' => 'Geolocation',
      'source' => 'GeolocationService',
      'pattern' => true,
    ]);
  }

  private function register(string $key, array $meta): void
  {
    $this->entries[$key] = array_merge($meta, ['key' => $key]);
  }

  /**
   * Get all registered cache entries with their current status.
   *
   * @return array<int, array{key: string, label: string, description: string, ttl: int|null, group: string, source: string, exists: bool, pattern?: bool, count?: int}>
   */
  public function getAll(): array
  {
    return collect($this->entries)
      ->map(function (array $entry) {
        if ($this->isPattern($entry['key'])) {
          $count = $this->countPatternKeys($entry['key']);
          $entry['exists'] = $count > 0;
          $entry['count'] = $count;
        } else {
          $entry['exists'] = Cache::has($entry['key']);
        }
        return $entry;
      })
      ->values()
      ->all();
  }

  /**
   * Flush a registered cache key.
   */
  public function flush(string $key): bool
  {
    if (!isset($this->entries[$key])) {
      return false;
    }

    if ($this->isPattern($key)) {
      return $this->flushPattern($key);
    }

    return Cache::forget($key);
  }

  /**
   * Get a single registered entry by key.
   */
  public function getEntry(string $key): ?array
  {
    return $this->entries[$key] ?? null;
  }

  private function isPattern(string $key): bool
  {
    return !empty($this->entries[$key]['pattern']);
  }

  private function countPatternKeys(string $key): int
  {
    $prefix = config('cache.prefix');
    $redisPattern = $prefix . $key;
    $keys = Redis::keys($redisPattern);

    return count($keys);
  }

  private function flushPattern(string $key): bool
  {
    $prefix = config('cache.prefix');
    $redisPattern = $prefix . $key;
    $keys = Redis::keys($redisPattern);

    if (empty($keys)) {
      return true;
    }

    foreach ($keys as $redisKey) {
      Redis::del($redisKey);
    }

    return true;
  }
}
