<?php

namespace App\Console\Commands;

use App\Models\TrafficLog;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista (y opcionalmente borra) traffic_logs de ruido: staging (*.new-site.dev),
 * localhost/host vacio y paths de escaneo de bots (.env, wp-admin, shell, etc.).
 *
 * Protege SIEMPRE el host propio de la app (derivado de APP_URL) + su www: nunca se borra
 * el trafico del panel admin ni paginas propias, sin hardcodear el dominio.
 *
 * Default: DRY RUN (solo reporta candidatos por regla). Borra unicamente con --force.
 * Sin FKs apuntando a traffic_logs, el delete no tiene cascada DB. Idempotente, chunked.
 */
class PurgeNoiseTrafficLogs extends Command
{
  protected $signature = 'traffic-logs:purge-noise
    {--force : Ejecuta el borrado (sin esto solo reporta)}
    {--batch=1000 : Filas por DELETE para acotar el lock}';

  protected $description = 'Borra traffic_logs de staging, hosts internos y escaneos de bots';

  /** Hosts a eliminar por completo: staging y localhost/vacio. */
  private const NOISE_HOST_LIKE = ['%.new-site.dev'];
  private const NOISE_HOST_EXACT = ['localhost', ''];

  /** Fragmentos de path de escaneo de bots (case-insensitive, LIKE %frag%). */
  private const BOT_PATH_FRAGMENTS = [
    '.env',
    'wp-admin',
    'wp-content',
    'wp-includes',
    'cgi-bin',
    'phpinfo',
    'webshell',
    'shell',
    '.git',
    'sendgrid',
    'twilio.env',
    'sftp-config',
    'config.json',
    '.well-known',
    '/vendor/',
    'sitemap',
    'robots.txt',
    '.jsp',
  ];

  public function handle(): int
  {
    $force = (bool) $this->option('force');
    $batch = max(1, (int) $this->option('batch'));

    $rules = [
      'staging/internal hosts' => fn(Builder $q) => $q->where(function (Builder $sub) {
        foreach (self::NOISE_HOST_LIKE as $pattern) {
          $sub->orWhere('host', 'like', $pattern);
        }
        $sub->orWhereIn('host', self::NOISE_HOST_EXACT);
      }),
      'bot-scan paths' => fn(Builder $q) => $q->where(function (Builder $sub) {
        foreach (self::BOT_PATH_FRAGMENTS as $frag) {
          // LOWER()+LIKE para ser case-insensitive y cross-DB (SQLite tests + PostgreSQL prod).
          $sub->orWhereRaw('LOWER(path_visited) LIKE ?', ['%' . strtolower($frag) . '%']);
        }
      }),
    ];

    $protectedHosts = $this->protectedHosts();
    if (!empty($protectedHosts)) {
      $this->info('Hosts protegidos (no se borran nunca): ' . implode(', ', $protectedHosts));
    }

    $report = [];
    $grandTotal = 0;

    foreach ($rules as $label => $scope) {
      $count = $this->protect($scope(TrafficLog::query()), $protectedHosts)->count();
      $report[] = [$label, $count];
      $grandTotal += $count;
    }

    $this->table(['Regla', 'Filas candidatas'], $report);
    $this->info('Total candidatas (puede haber solape entre reglas): ' . $grandTotal);

    if (!$force) {
      $this->warn('DRY RUN — no se borro nada. Reejecuta con --force para borrar.');

      return self::SUCCESS;
    }

    $deleted = 0;
    foreach ($rules as $label => $scope) {
      do {
        $ids = $this->protect($scope(TrafficLog::query()), $protectedHosts)
          ->limit($batch)
          ->pluck('id');
        if ($ids->isEmpty()) {
          break;
        }
        $deleted += TrafficLog::whereIn('id', $ids)->delete();
      } while ($ids->count() === $batch);
    }

    $this->info("Borradas {$deleted} filas de traffic_logs.");

    return self::SUCCESS;
  }

  /**
   * Host propio de la app (derivado de APP_URL) + su variante www. Nunca se borran:
   * protege el panel admin y cualquier pagina propia sin hardcodear el dominio.
   *
   * @return array<int, string>
   */
  private function protectedHosts(): array
  {
    $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    $appHost = preg_replace('/^www\./', '', rtrim($appHost, '.'));

    if ($appHost === '') {
      return [];
    }

    return [$appHost, "www.{$appHost}"];
  }

  /**
   * @param array<int, string> $protectedHosts
   */
  private function protect(Builder $query, array $protectedHosts): Builder
  {
    if (!empty($protectedHosts)) {
      $placeholders = implode(', ', array_fill(0, count($protectedHosts), '?'));
      $query->whereRaw("LOWER(host) NOT IN ({$placeholders})", $protectedHosts);
    }

    return $query;
  }
}
