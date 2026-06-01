<?php

namespace App\Console\Commands;

use App\Models\TrafficLog;
use App\Services\LandingPageResolverService;
use Illuminate\Console\Command;

/**
 * Backfilea traffic_logs.landing_id / landing_page_version_id sobre data historica.
 *
 * Estrategia por pares distintos host+path (un resolve por par, no por fila):
 *  - Pase A: vincula filas sin landing_id.
 *  - Pase B: completa version_id en filas que ya tenian landing_id pero no version.
 *
 * Idempotente. Reporta los hosts que no resolvieron a ninguna landing (ordenados por
 * hits) para alimentar el ticket de alias de hosts.
 */
class BackfillTrafficLogsLandingId extends Command
{
  protected $signature = 'traffic-logs:backfill-landing-id
    {--batch=500 : Cantidad de filas por UPDATE (acota el lock en tablas grandes)}
    {--dry-run : Resuelve y reporta sin escribir}
    {--limit= : Limita la cantidad de pares host+path distintos procesados}';

  protected $description = 'Vincula traffic_logs historicos con su landing_id y landing_page_version_id';

  public function handle(LandingPageResolverService $resolver): int
  {
    $dryRun = (bool) $this->option('dry-run');
    $batch = max(1, (int) $this->option('batch'));
    $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

    if ($dryRun) {
      $this->warn('DRY RUN — no se escribira nada.');
    }

    // Pares distintos host+path que todavia necesitan trabajo, con su conteo de hits.
    $pairsQuery = TrafficLog::query()
      ->selectRaw('host, path_visited, COUNT(*) as hits')
      ->where(function ($query) {
        $query->whereNull('landing_id')->orWhereNull('landing_page_version_id');
      })
      ->groupBy('host', 'path_visited')
      ->orderByDesc('hits');

    if ($limit !== null) {
      $pairsQuery->limit($limit);
    }

    $pairs = $pairsQuery->get();

    $stats = [
      'total_pairs' => $pairs->count(),
      'pairs_resolved_landing' => 0,
      'pairs_resolved_version' => 0,
      'pairs_unresolved' => 0,
      'rows_linked' => 0,
      'versions_completed' => 0,
    ];

    $unresolved = [];

    $bar = $this->output->createProgressBar($pairs->count());
    $bar->start();

    foreach ($pairs as $pair) {
      $host = (string) $pair->host;
      $path = (string) $pair->path_visited;

      $resolved = $resolver->resolve(null, $host, $path);

      if ($resolved['landing_id'] === null) {
        $stats['pairs_unresolved']++;
        $unresolved[] = ['host' => $host, 'path' => $path, 'hits' => (int) $pair->hits];
        $bar->advance();
        continue;
      }

      $stats['pairs_resolved_landing']++;
      if ($resolved['landing_page_version_id'] !== null) {
        $stats['pairs_resolved_version']++;
      }

      if (!$dryRun) {
        $stats['rows_linked'] += $this->linkUnlinkedRows($host, $path, $resolved, $batch);

        if ($resolved['landing_page_version_id'] !== null) {
          $stats['versions_completed'] += $this->completeMissingVersions($host, $path, (int) $resolved['landing_page_version_id'], $batch);
        }
      }

      $bar->advance();
    }

    $bar->finish();
    $this->newLine(2);

    $this->renderStats($stats);
    $this->renderUnresolved($unresolved);

    return self::SUCCESS;
  }

  /**
   * Pase A: filas sin landing_id. Chunked por id para acotar el lock.
   *
   * @param array{landing_id: int|null, landing_page_version_id: int|null} $resolved
   */
  private function linkUnlinkedRows(string $host, string $path, array $resolved, int $batch): int
  {
    $linked = 0;

    do {
      $ids = TrafficLog::where('host', $host)->where('path_visited', $path)->whereNull('landing_id')->limit($batch)->pluck('id');

      if ($ids->isEmpty()) {
        break;
      }

      $linked += TrafficLog::whereIn('id', $ids)->update([
        'landing_id' => $resolved['landing_id'],
        'landing_page_version_id' => $resolved['landing_page_version_id'],
      ]);
    } while ($ids->count() === $batch);

    return $linked;
  }

  /**
   * Pase B: filas con landing_id ya seteado pero version null. Chunked por id.
   */
  private function completeMissingVersions(string $host, string $path, int $versionId, int $batch): int
  {
    $completed = 0;

    do {
      $ids = TrafficLog::where('host', $host)
        ->where('path_visited', $path)
        ->whereNotNull('landing_id')
        ->whereNull('landing_page_version_id')
        ->limit($batch)
        ->pluck('id');

      if ($ids->isEmpty()) {
        break;
      }

      $completed += TrafficLog::whereIn('id', $ids)->update(['landing_page_version_id' => $versionId]);
    } while ($ids->count() === $batch);

    return $completed;
  }

  /**
   * @param array<string, int> $stats
   */
  private function renderStats(array $stats): void
  {
    $this->table(['Metric', 'Value'], collect($stats)->map(fn($value, $key) => [$key, $value])->values()->all());
  }

  /**
   * @param array<int, array{host: string, path: string, hits: int}> $unresolved
   */
  private function renderUnresolved(array $unresolved): void
  {
    if (empty($unresolved)) {
      return;
    }

    usort($unresolved, fn($a, $b) => $b['hits'] <=> $a['hits']);

    $this->newLine();
    $this->warn('Hosts/paths sin resolver (candidatos a aliasear o crear como landing):');
    $this->table(['Host', 'Path', 'Hits'], array_map(fn($row) => [$row['host'], $row['path'], $row['hits']], $unresolved));
  }
}
