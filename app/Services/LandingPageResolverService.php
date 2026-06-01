<?php

namespace App\Services;

use App\Models\LandingPage;
use App\Models\LandingPageVersion;
use Illuminate\Support\Facades\Cache;

/**
 * Resuelve la LandingPage (y su version) que origino una visita.
 *
 * Dos pasos:
 *  1. landing_id: usa el id explicito si vino y existe; si no, lo deduce por host.
 *  2. landing_page_version_id: matchea el path visitado contra las versions de esa landing.
 *
 * La deduccion por host (matchLandingByHost) cubre apex + www. Los subdominios
 * arbitrarios (quotes.*, offer.*, etc.) NO resuelven todavia — eso queda para el
 * ticket de alias de hosts (tabla landing_page_hosts), que se enchufa aca.
 */
class LandingPageResolverService
{
  private const CACHE_TTL = 600;

  /** Sentinela para cachear "no hubo match" (los ids reales arrancan en 1). */
  private const NO_MATCH = 0;

  /**
   * @return array{landing_id: int|null, landing_page_version_id: int|null}
   */
  public function resolve(?int $landingId, string $host, string $pathVisited): array
  {
    $resolvedLandingId = null;

    if ($landingId !== null && LandingPage::whereKey($landingId)->exists()) {
      $resolvedLandingId = $landingId;
    } else {
      $resolvedLandingId = $this->matchLandingByHost($host);
    }

    $resolvedVersionId = null;
    if ($resolvedLandingId !== null) {
      $resolvedVersionId = $this->matchVersionByPath($resolvedLandingId, $pathVisited);
    }

    return [
      'landing_id' => $resolvedLandingId,
      'landing_page_version_id' => $resolvedVersionId,
    ];
  }

  /**
   * Deduce la landing comparando el host (normalizado) contra el host de landing_pages.url.
   *
   * Costura para el ticket de alias de hosts: cuando exista landing_page_hosts,
   * el lookup de alias se agrega aca antes/junto al match por url.
   */
  private function matchLandingByHost(string $host): ?int
  {
    $normalized = $this->normalizeHost($host);
    if ($normalized === '') {
      return null;
    }

    $cacheKey = "landing-pages:resolver:host:{$normalized}";
    $cached = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($normalized) {
      $landing = LandingPage::query()
        ->select(['id', 'url', 'active'])
        ->orderByDesc('active')
        ->orderByDesc('id')
        ->get()
        ->first(function (LandingPage $landing) use ($normalized) {
          $landingHost = $this->normalizeHost((string) parse_url((string) $landing->url, PHP_URL_HOST));

          return $landingHost !== '' && $landingHost === $normalized;
        });

      return $landing?->id ?? self::NO_MATCH;
    });

    return $cached === self::NO_MATCH ? null : $cached;
  }

  /**
   * Matchea el path visitado contra las versions de la landing (normalizando trailing slash).
   * Prioridad: status=true primero, despues id DESC.
   */
  private function matchVersionByPath(int $landingId, string $pathVisited): ?int
  {
    $normalizedPath = $this->normalizePath($pathVisited);

    $cacheKey = "landing-pages:resolver:version:{$landingId}:" . md5($normalizedPath);
    $cached = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($landingId, $normalizedPath) {
      $version = LandingPageVersion::query()
        ->where('landing_page_id', $landingId)
        ->orderByDesc('status')
        ->orderByDesc('id')
        ->get()
        ->first(function (LandingPageVersion $version) use ($normalizedPath) {
          return $this->normalizePath((string) $version->path) === $normalizedPath;
        });

      return $version?->id ?? self::NO_MATCH;
    });

    return $cached === self::NO_MATCH ? null : $cached;
  }

  /**
   * minusculas + sin punto final (FQDN) + sin www. inicial.
   */
  private function normalizeHost(string $host): string
  {
    $host = strtolower(trim($host));
    $host = rtrim($host, '.');

    if (str_starts_with($host, 'www.')) {
      $host = substr($host, 4);
    }

    return $host;
  }

  /**
   * Recorta espacios y trailing slash para comparar paths de forma estable.
   */
  private function normalizePath(string $path): string
  {
    return rtrim(trim($path), '/');
  }
}
