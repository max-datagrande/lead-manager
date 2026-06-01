<?php

namespace Database\Seeders;

use App\Models\LandingPage;
use App\Models\LandingPageVersion;
use App\Models\TrafficLog;
use App\Models\User;
use App\Models\Vertical;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Demo para validar LandingPageResolverService + el command traffic-logs:backfill-landing-id.
 *
 * Crea 2 landings con versions y ~50 traffic_logs SIN vincular (landing_id null), repartidos
 * en categorias que ejercitan cada rama de resolucion. Despues de correr el backfill, cada
 * categoria debe resolver al landing_id/version_id esperado de la tabla de abajo.
 */
class LandingResolutionDemoSeeder extends Seeder
{
  public function run(): void
  {
    // LandingPage/Vertical setean user_id desde Auth::id() en su hook creating.
    $user = User::firstOrCreate(['email' => 'demo-resolver@example.com'], ['name' => 'Demo Resolver', 'password' => bcrypt('password')]);
    Auth::login($user);

    $vertical = Vertical::factory()->create(['name' => 'Demo Vertical ' . Str::random(4)]);

    // Landing A — moonautoinsurance.com con dos versions.
    $landingA = LandingPage::create([
      'name' => 'Moon Auto Insurance',
      'url' => 'https://moonautoinsurance.com/',
      'is_external' => false,
      'vertical_id' => $vertical->id,
      'active' => true,
    ]);
    $aOffers = LandingPageVersion::create(['landing_page_id' => $landingA->id, 'name' => 'Offers', 'path' => '/offers/', 'status' => true]);
    $aQuote = LandingPageVersion::create(['landing_page_id' => $landingA->id, 'name' => 'Quote v2', 'path' => '/quote/v2', 'status' => true]);

    // Landing B — top-carinsurance.com con una version raiz.
    $landingB = LandingPage::create([
      'name' => 'Top Car Insurance',
      'url' => 'https://top-carinsurance.com/',
      'is_external' => false,
      'vertical_id' => $vertical->id,
      'active' => true,
    ]);
    $bRoot = LandingPageVersion::create(['landing_page_id' => $landingB->id, 'name' => 'Home', 'path' => '/', 'status' => true]);

    // [host, path_visited, cantidad, etiqueta de expectativa]
    $categories = [
      ['moonautoinsurance.com', '/offers/', 12, 'A + version Offers'],
      ['www.moonautoinsurance.com', '/quote/v2', 6, 'A + version Quote (www == apex)'],
      ['MOONAUTOINSURANCE.COM', '/offers/', 3, 'A + version Offers (mayusculas)'],
      ['moonautoinsurance.com.', '/', 3, 'A + version null (punto final, sin version /)'],
      ['moonautoinsurance.com', '/landing-sin-version', 4, 'A + version null (path sin match)'],
      ['quotes.moonautoinsurance.com', '/offers/', 6, 'SIN RESOLVER (subdominio arbitrario)'],
      ['top-carinsurance.com', '/', 8, 'B + version Home'],
      ['www.top-carinsurance.com', '/', 4, 'B + version Home (www)'],
      ['totally-unknown-domain.test', '/', 4, 'SIN RESOLVER (host desconocido)'],
    ];

    $created = 0;
    foreach ($categories as [$host, $path, $count, $label]) {
      for ($i = 0; $i < $count; $i++) {
        TrafficLog::create([
          'id' => (string) Str::uuid(),
          'fingerprint' => hash('sha256', $host . $path . $i . Str::random(8)),
          'visit_date' => now()->subDays(rand(0, 30))->toDateString(),
          'visit_count' => 1,
          'ip_address' => fake()->ipv4(),
          'user_agent' => fake()->userAgent(),
          'host' => $host,
          'path_visited' => $path,
          'landing_id' => null,
          'landing_page_version_id' => null,
          'is_bot' => false,
        ]);
        $created++;
      }
    }

    $this->command->info("Seed listo: 2 landings, 3 versions, {$created} traffic_logs sin vincular.");
    $this->command->info('Expectativas por categoria (correr el backfill para vincular):');
    $this->command->table(['Host', 'Path', 'Cantidad', 'Esperado'], array_map(fn($c) => [$c[0], $c[1], $c[2], $c[3]], $categories));
    $this->command->info("Landing A id={$landingA->id} (versions: offers={$aOffers->id}, quote={$aQuote->id})");
    $this->command->info("Landing B id={$landingB->id} (version: home={$bRoot->id})");
  }
}
