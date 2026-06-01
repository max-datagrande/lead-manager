<?php

namespace App\Console\Commands;

use App\Models\LandingPage;
use App\Models\User;
use App\Models\Vertical;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Crea/asegura las landings + versions curadas a partir de la revision manual de
 * traffic_logs (reports/landing-hosts-review.md). Idempotente: usa el host para reusar
 * landings existentes y firstOrCreate para versions y verticals.
 *
 * Modelo: una LandingPage es un "padre de versiones" anclado a UN host. Cada subdominio
 * (quotes., offer., chat., etc.) es su PROPIA landing con sus propias versions. El caso
 * www. NO se crea aparte: el resolver lo pliega al host base (www.X == X), asi que solo
 * hay que asegurar que la landing base tenga esas versions.
 *
 * Excluye staging (*.new-site.dev), bots y paths legales/ruido marcados NO en la revision.
 */
class PopulateCuratedLandingPages extends Command
{
  protected $signature = 'landing-pages:populate-curated
    {--dry-run : Muestra lo que haria sin escribir}
    {--user-id= : Usuario al que atribuir las creaciones (hooks creating); default: primer usuario}';

  protected $description = 'Crea las landings + versions curadas desde la revision de traffic_logs';

  /**
   * Catalogo curado. paths ya normalizados (slash inicial, sin slash final), deduplicados.
   * '/' = home.
   *
   * @var array<int, array{host: string, vertical: string, paths: array<int, string>}>
   */
  private const CATALOG = [
    // --- Auto Insurance ---
    [
      'host' => 'top-carinsurance.com',
      'vertical' => 'Auto Insurance',
      'paths' => [
        '/',
        '/rates',
        '/auto',
        '/offers',
        '/soffers',
        '/results',
        '/auto-insurance-quotes',
        '/explore',
        '/home',
        '/quotes',
        '/thankyou',
        '/es',
      ],
    ],
    ['host' => 'quotes.top-carinsurance.com', 'vertical' => 'Auto Insurance', 'paths' => ['/', '/rates', '/quotes']],
    ['host' => 'offer.top-carinsurance.com', 'vertical' => 'Auto Insurance', 'paths' => ['/', '/quotes']],
    ['host' => 'lav.top-carinsurance.com', 'vertical' => 'Auto Insurance', 'paths' => ['/', '/rates']],
    ['host' => 'new.top-carinsurance.com', 'vertical' => 'Auto Insurance', 'paths' => ['/']],
    ['host' => 'a-rates.com', 'vertical' => 'Auto Insurance', 'paths' => ['/']],
    ['host' => 'top.a-rates.com', 'vertical' => 'Auto Insurance', 'paths' => ['/']],
    ['host' => 'moonautoinsurance.com', 'vertical' => 'Auto Insurance', 'paths' => ['/', '/offers']],
    ['host' => 'saverica.com', 'vertical' => 'Auto Insurance', 'paths' => ['/', '/survey', '/offerwall']],
    ['host' => 'bizhield.com', 'vertical' => 'Auto Insurance', 'paths' => ['/quotes', '/business-car-insurance']],
    // --- MVA ---
    ['host' => 'mytrusted-recovery.com', 'vertical' => 'MVA', 'paths' => ['/full-form', '/qs202510', '/thank-you', '/injury']],
    ['host' => 'trusted-claim-assistance.com', 'vertical' => 'MVA', 'paths' => ['/', '/full-form', '/qs202510', '/thank-you']],
    ['host' => 'chat.trusted-claim-assistance.com', 'vertical' => 'MVA', 'paths' => ['/', '/full-form']],
    ['host' => 'survey.trusted-claim-assistance.com', 'vertical' => 'MVA', 'paths' => ['/']],
    [
      'host' => 'justicepayout.com',
      'vertical' => 'MVA',
      'paths' => ['/', '/vehicle-accident', '/vehicle-accident-v2', '/es/vehicle-accident', '/es/vehicle-accident-v2', '/thank-you'],
    ],
    ['host' => 'disaster-recoveryassistance.com', 'vertical' => 'MVA', 'paths' => ['/']],
    // --- Distribution ---
    ['host' => 'totalfinancialbenefits.com', 'vertical' => 'Distribution', 'paths' => ['/', '/welcome']],
    // --- Pet Insurance ---
    ['host' => 'pawcovered.com', 'vertical' => 'Pet Insurance', 'paths' => ['/', '/rates']],
  ];

  public function handle(): int
  {
    $dryRun = (bool) $this->option('dry-run');

    $user = $this->option('user-id') !== null ? User::find((int) $this->option('user-id')) : User::query()->orderBy('id')->first();

    if (!$user) {
      $this->error('No hay usuario para atribuir las creaciones. Crea uno o pasa --user-id.');

      return self::FAILURE;
    }

    // Los hooks creating de Vertical/LandingPage toman user_id de Auth::id().
    Auth::login($user);

    if ($dryRun) {
      $this->warn('DRY RUN — no se escribira nada.');
    }

    $rows = [];
    $landingsCreated = 0;
    $versionsCreated = 0;

    foreach (self::CATALOG as $entry) {
      $vertical = $this->resolveVertical($entry['vertical'], $dryRun);
      $landing = $this->findLandingByHost($entry['host']);
      $landingAction = $landing ? 'existe' : 'crear';

      if (!$landing && !$dryRun) {
        $landing = LandingPage::create([
          'name' => $this->landingName($entry['host']),
          'url' => "https://{$entry['host']}/",
          'is_external' => false,
          'vertical_id' => $vertical?->id ?? 0,
          'active' => true,
        ]);
      }

      if (!$landing) {
        $landingsCreated++;
      }

      $newVersions = 0;
      foreach ($entry['paths'] as $path) {
        $exists = $landing && $landing->versions()->where('path', $path)->exists();
        if ($exists) {
          continue;
        }

        $newVersions++;
        if (!$dryRun && $landing) {
          $landing->versions()->create([
            'name' => $this->versionName($path),
            'path' => $path,
            'status' => true,
          ]);
        }
      }
      $versionsCreated += $newVersions;

      $rows[] = [$entry['host'], $entry['vertical'], $landingAction, count($entry['paths']), $newVersions];
    }

    $this->table(['Host', 'Vertical', 'Landing', 'Versions catalogo', 'Versions nuevas'], $rows);
    $this->info(($dryRun ? '[dry-run] ' : '') . "Landings nuevas: {$landingsCreated} | Versions nuevas: {$versionsCreated}");
    $this->newLine();
    $this->warn(
      'Cada subdominio es su propia landing. www. se pliega al host base (resolver). Staging/bots/ruido se limpian aparte (ver task de limpieza).',
    );

    return self::SUCCESS;
  }

  private function resolveVertical(string $name, bool $dryRun): ?Vertical
  {
    $vertical = Vertical::query()
      ->whereRaw('LOWER(name) = ?', [strtolower($name)])
      ->first();

    if ($vertical) {
      return $vertical;
    }

    if ($dryRun) {
      return null;
    }

    return Vertical::create(['name' => $name, 'active' => true]);
  }

  private function findLandingByHost(string $host): ?LandingPage
  {
    return LandingPage::all()->first(function (LandingPage $landing) use ($host) {
      $landingHost = strtolower((string) parse_url((string) $landing->url, PHP_URL_HOST));
      $landingHost = preg_replace('/^www\./', '', rtrim($landingHost, '.'));

      return $landingHost === $host;
    });
  }

  private function landingName(string $host): string
  {
    $base = preg_replace('/\.[a-z]+$/', '', $host);

    return Str::of($base)
      ->replace(['-', '.'], ' ')
      ->title()
      ->toString();
  }

  private function versionName(string $path): string
  {
    if ($path === '/') {
      return 'Home';
    }

    return Str::of($path)
      ->trim('/')
      ->replace(['-', '/'], ' ')
      ->title()
      ->toString();
  }
}
