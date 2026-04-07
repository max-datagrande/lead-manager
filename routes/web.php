<?php

use App\Http\Controllers\Admin\WhitelistEntryController;
use App\Http\Controllers\CatalystController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Form\FieldController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\Logs\LeadDispatchLogController;
use App\Http\Controllers\Logs\OfferwallMixLogController;
use App\Http\Controllers\Offerwall\TesterController;
use App\Http\Controllers\OfferwallController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\PingPost\BuyerController;
use App\Http\Controllers\PingPost\WorkflowController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\InternalPostbackController;
use App\Http\Controllers\PostbackAssociationController;
use App\Http\Controllers\PostbackController;
use App\Http\Controllers\PostbackExecutionsController;
use App\Http\Controllers\PostbackQueueController;
use App\Http\Controllers\VerticalController;
use App\Http\Controllers\VisitorController;
use App\Http\Controllers\VpsMetricsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
  Route::get('/', [DashboardController::class, 'index'])->name('home');
  // Performance metrics
  Route::get('performance', [PerformanceController::class, 'index'])->name('performance.index');
  // VPS metrics
  Route::post('vps-metrics/refresh', [VpsMetricsController::class, 'refresh'])->name('vps.refresh');
  // Visitors
  Route::get('visitors', [VisitorController::class, 'index'])->name('visitors.index');
  // Postbacks — new generic module (list)
  Route::prefix('postbacks')
    ->name('postbacks.')
    ->group(function () {
      Route::get('/', [PostbackController::class, 'index'])->name('index');
      Route::get('/create', [PostbackController::class, 'create'])->name('create');
      Route::post('/', [PostbackController::class, 'store'])->name('store');
      Route::get('/{postback}/edit', [PostbackController::class, 'edit'])
        ->whereNumber('postback')
        ->name('edit');
      Route::put('/{postback}', [PostbackController::class, 'update'])
        ->whereNumber('postback')
        ->name('update');
      Route::delete('/{postback}', [PostbackController::class, 'destroy'])
        ->whereNumber('postback')
        ->name('destroy');
      // Internal fire (manual trigger)
      Route::prefix('internal')
        ->name('internal.')
        ->group(function () {
          Route::get('/{postback}/fire', [InternalPostbackController::class, 'fireForm'])
            ->whereNumber('postback')
            ->name('fire-form');
          Route::post('/resolve-tokens', [InternalPostbackController::class, 'resolveTokens'])->name('resolve-tokens');
          Route::post('/{postback}/fire', [InternalPostbackController::class, 'fire'])
            ->whereNumber('postback')
            ->name('fire');
        });
      // Associations (agnostic: workflow ↔ postback, etc.)
      Route::post('/associations', [PostbackAssociationController::class, 'store'])->name('associations.store');
      Route::delete('/associations/{source}/{sourceId}/{postbackId}', [PostbackAssociationController::class, 'destroy'])
        ->whereNumber(['sourceId', 'postbackId'])
        ->name('associations.destroy');
      Route::post('/associations/fire-for-dispatch', [PostbackAssociationController::class, 'fireForDispatch'])->name(
        'associations.fire-for-dispatch',
      );
      Route::post('/associations/preview-for-dispatch', [PostbackAssociationController::class, 'previewForDispatch'])->name(
        'associations.preview-for-dispatch',
      );
      // Executions (new fire system)
      Route::prefix('executions')
        ->name('executions.')
        ->group(function () {
          Route::get('/', [PostbackExecutionsController::class, 'index'])->name('index');
          Route::get('/{execution}/dispatch-logs', [PostbackExecutionsController::class, 'dispatchLogs'])
            ->whereNumber('execution')
            ->name('dispatch-logs');
        });
      // NI Queue (legacy)
      Route::prefix('queue-legacy')
        ->name('queue-legacy.')
        ->group(function () {
          Route::get('/', [PostbackQueueController::class, 'index'])->name('index');
          Route::delete('/{postbackQueue}', [PostbackQueueController::class, 'destroy'])
            ->whereNumber('postbackQueue')
            ->name('destroy');
          Route::get('/{postbackId}/api-requests', [PostbackQueueController::class, 'getApiRequests'])
            ->whereNumber('postbackId')
            ->name('api-requests');
          Route::patch('/{postbackQueue}/status', [PostbackQueueController::class, 'updateStatus'])
            ->whereNumber('postbackQueue')
            ->name('updateStatus');
          Route::post('/{postbackQueue}/force-sync', [PostbackQueueController::class, 'forceSync'])
            ->whereNumber('postbackQueue')
            ->name('force-sync');
        });
    });
  // Platforms — independent module
  Route::resource('platforms', PlatformController::class)
    ->except(['show', 'create', 'edit'])
    ->whereNumber('platform');
  // Companies
  Route::resource('companies', CompanyController::class)
    ->except(['show', 'create', 'edit'])
    ->whereNumber('company');
  // Integrations
  Route::post('integrations/{integration}/environments/{environment}/test', [IntegrationController::class, 'test'])
    ->whereNumber(['integration', 'environment'])
    ->name('integrations.test');
  Route::post('integrations/{integration}/duplicate', [IntegrationController::class, 'duplicate'])
    ->whereNumber('integration')
    ->name('integrations.duplicate');
  Route::resource('integrations', IntegrationController::class)->whereNumber('integration');

  // Offerwalls
  Route::prefix('offerwall')
    ->middleware(['role:admin,manager'])
    ->group(function () {
      Route::get('conversions', [OfferwallController::class, 'conversions'])->name('offerwall.conversions');
      Route::get('conversions/report', [OfferwallController::class, 'conversionReport'])->name('offerwall.conversions.report');
      // Offerwall Tester
      Route::prefix('tester')
        ->name('offerwall.tester.')
        ->group(function () {
          Route::get('/', [TesterController::class, 'index'])->name('index');
          Route::get('/{integration}/fields', [TesterController::class, 'getFields'])
            ->whereNumber('integration')
            ->name('fields');
          Route::post('/prepare', [TesterController::class, 'prepare'])->name('prepare');
          Route::post('/execute', [TesterController::class, 'execute'])->name('execute');
        });
    });
  Route::resource('offerwall', OfferwallController::class)
    ->parameters(['offerwall' => 'offerwallMix'])
    ->whereNumber('offerwallMix');

  // Verticals
  Route::resource('verticals', VerticalController::class)->whereNumber('vertical');
  Route::resource('landing_pages', LandingPageController::class)->whereNumber('landing_page');

  // Forms
  Route::prefix('forms')->group(function () {
    Route::resource('fields', FieldController::class)->whereNumber('field');
  });
  // Whitelist
  Route::get('whitelist', [WhitelistEntryController::class, 'index'])->name('whitelist.index');

  // Logs
  Route::prefix('logs')
    ->name('logs.')
    ->group(function () {
      Route::resource('offerwall-mixes', OfferwallMixLogController::class)
        ->only(['index', 'show'])
        ->parameters(['offerwall-mixes' => 'offerwallMixLog'])
        ->whereNumber('offerwallMixLog');
      Route::resource('dispatches', LeadDispatchLogController::class)
        ->only(['index', 'show'])
        ->whereNumber('dispatch');
    });

  // Share Leads — Ping Post / Post Only
  Route::prefix('ping-post')
    ->name('ping-post.')
    ->middleware(['role:admin,manager'])
    ->group(function () {
      Route::post('buyers/{buyer}/duplicate', [BuyerController::class, 'duplicate'])
        ->whereNumber('buyer')
        ->name('buyers.duplicate');
      Route::resource('buyers', BuyerController::class)->whereNumber('buyer');
      Route::post('workflows/{workflow}/duplicate', [WorkflowController::class, 'duplicate'])
        ->whereNumber('workflow')
        ->name('workflows.duplicate');
      Route::resource('workflows', WorkflowController::class)->whereNumber('workflow');
      Route::resource('dispatches', LeadDispatchLogController::class)
        ->only(['index', 'show'])
        ->whereNumber('dispatch');
      Route::get('dispatches/{dispatch}/timeline', [LeadDispatchLogController::class, 'timeline'])
        ->whereNumber('dispatch')
        ->name('dispatches.timeline');
    });
});

Route::prefix('catalyst')->group(function () {
  // Catalyst Test Route
  Route::get('/test', function () {
    return view('catalyst-test');
  })->name('catalyst.test');

  // Catalyst Test Route (Manual Loader)
  Route::get('/test-manual', function () {
    return view('catalyst-test-manual');
  })->name('catalyst.test-manual');

  // Catalyst Engine Route
  Route::get('/engine.js', [CatalystController::class, 'loader']);

  // Catalyst Direct Asset Route (Proxy/Redirect)
  Route::get('/{version}.js', [CatalystController::class, 'asset'])->where('version', 'v[0-9]+\.[0-9]+');
});

// Catalyst

require __DIR__ . '/settings.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/docs.php';
require __DIR__ . '/auth.php';

/*

Route::middleware(['auth', 'verified'])->group(function () {
  Route::get('/', [DashboardController::class, 'index'])->name('home');
  Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
  //Visitors
  Route::get('visitors', [TrafficController::class, 'index'])->name('visitors.index');
  //Fields
  Route::resource('fields', FieldController::class);
  Route::resource('forms', FormController::class);
  // Ruta de exportación de leads (debe ir antes del resource)
  Route::get('leads/export', [LeadController::class, 'export'])->name('leads.export');
  Route::resource('leads', LeadController::class);
  Route::resource('sales', SaleController::class);
  Route::resource('campaigns', CampaignController::class);
});

// Project, Landing, and Host Routes
Route::resource('projects', ProjectController::class);
Route::resource('landings', LandingController::class);
Route::post('landings/{landing}/hosts', [HostController::class, 'store'])->name('landings.hosts.store');
Route::put('landings/{landing}/hosts/{host}', [HostController::class, 'update'])->name('landings.hosts.update');
Route::delete('landings/{landing}/hosts/{host}', [HostController::class, 'destroy'])->name('landings.hosts.destroy');
require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/admin.php';


*/
