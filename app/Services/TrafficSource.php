<?php

namespace App\Services;

use Illuminate\Http\Request;

class TrafficSource
{
  function __construct(protected Request $request) {}
  public function setTrafficSource(): void
  {
    $host          = $this->request->getHost();
    $params        = $this->getSession('query_params') ?? [];
    $initialRef    = $this->getSession('initial_referrer') ?? [];
    $medium        = 'direct';
    $source        = 'direct';
    /* ------------------------------------------------------------------
     | 1. Campañas pagas con cptype  (ads)
     * ------------------------------------------------------------------*/
    $hasCptype = ! empty($params['cptype']);
    if ($hasCptype) {
      $cptype   = strtoupper($params['cptype']);
      $campaign = collect(config('campaigns'))
        ->firstWhere('cptype', strtoupper($params['cptype']));

      $medium = 'ads';
      $source = $campaign['vendor'] ?? $cptype;    // fallback al propio código
      $this->setSession('traffic_medium', $medium);
      $this->setSession('traffic_source', $source);
      return;
    }

    /* ------------------------------------------------------------------
     | 2. Initial referrer (si aún somos direct)
     * ------------------------------------------------------------------*/
    if ($medium === 'direct' && $initialRef) {
      $refHost = parse_url($initialRef, PHP_URL_HOST) ?? '';
      //Gettint root domain
      $parts = explode('.', $host);
      $rootDomain = count($parts) > 2
        ? implode('.', array_slice($parts, -2))
        : $host;
      //Contains root domain in referrer
      $containsRoot = strpos($refHost, $rootDomain) !== false;
      // omitimos tráfico interno
      if (!$containsRoot) {
        /* === 2a) Buscadores → organic ============================ */
        $search = [
          '/google\./i',
          '/bing\.com$/i',
          '/yahoo\./i',
          '/duckduckgo\.com$/i',
        ];
        if ($this->preg_match_any($search, $refHost)) {
          $medium = 'organic';
          $source = $this->host_base($refHost);       // google, bing, etc.
        }

        /* === 2b) Redes sociales → social ========================= */
        $social = [
          '/facebook\.com$/i',
          '/t\.co$/i',          // X/Twitter
          '/instagram\.com$/i',
          '/linkedin\.com$/i',
          '/reddit\.com$/i',
        ];
        if ($medium === 'direct' && $this->preg_match_any($social, $refHost)) {
          $medium = 'social';
          $source = $this->host_base($refHost);       // facebook, t.co, …
        }

        /* === 2c) Otros → referral  ============================== */
        if ($medium === 'direct') {             // no match previo
          $medium = 'referral';
          $source = $refHost;
        }
      }
    }
    /* ------------------------------------------------------------------
     | 3. Persistimos en sesión & request
     * ------------------------------------------------------------------*/
    $this->setSession('traffic_medium', $medium);
    $this->setSession('traffic_source', $source);
  }
}
