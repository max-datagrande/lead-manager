<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\VisitorController;
use App\Http\Controllers\PostbackController;
use App\Http\Controllers\Form\FieldController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\Admin\WhitelistEntryController;
use App\Http\Controllers\OfferwallController;
use App\Http\Controllers\Logs\OfferwallMixLogController;
use App\Http\Controllers\CatalystController;

Route::middleware(['auth', 'verified'])->group(function () {
  Route::get('/', function () {
    return Inertia::render('dashboard');
  })->name('home');
  //Visitors
  Route::get('visitors', [VisitorController::class, 'index'])->name('visitors.index');
  //Postbacks
  Route::prefix('postbacks')->name('postbacks.')->group(function () {
    Route::get('/', [PostbackController::class, 'index'])->name('index');
    Route::delete('/{postback}', [PostbackController::class, 'destroy'])->name('destroy');
    Route::get('/{postbackId}/api-requests', [PostbackController::class, 'getApiRequests'])->name('api-requests');
    Route::patch('/{postback}/status', [PostbackController::class, 'updateStatus'])->name('updateStatus');
    Route::post('/{postback}/force-sync', [PostbackController::class, 'forceSync'])->name('force-sync');
  });
  //Companies
  Route::resource('companies', CompanyController::class)->except(['show', 'create', 'edit']);
  //Integrations
  Route::post('integrations/{integration}/environments/{environment}/test', [IntegrationController::class, 'test'])->name('integrations.test');
  Route::post('integrations/{integration}/duplicate', [IntegrationController::class, 'duplicate'])->name('integrations.duplicate');
  Route::resource('integrations', IntegrationController::class);

  //Offerwalls
  Route::prefix('offerwall')->group(function () {
    Route::get('conversions', [OfferwallController::class, 'conversions'])->name('offerwall.conversions');
  });
  Route::resource('offerwall', OfferwallController::class)->parameters(['offerwall' => 'offerwallMix']);

  //Forms
  Route::prefix('forms')->group(function () {
    Route::resource('fields', FieldController::class);
  });
  //Whitelist
  Route::get('whitelist', [WhitelistEntryController::class, 'index'])->name('whitelist.index');

  // Logs
  Route::prefix('logs')->name('logs.')->group(function () {
    Route::resource('offerwall-mixes', OfferwallMixLogController::class)
      ->only(['index', 'show'])
      ->parameters(['offerwall-mixes' => 'offerwallMixLog']);
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

//Catalyst

require __DIR__ . '/settings.php';
require __DIR__ . '/admin.php';
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
