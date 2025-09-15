<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\TrafficController;
use App\Http\Controllers\PostbackController;
use App\Http\Controllers\Form\FieldController;
use App\Http\Controllers\Admin\WhitelistEntryController;

Route::middleware(['auth', 'verified'])->group(function () {
  Route::get('/', function () {
    return Inertia::render('dashboard');
  })->name('home');
  //Visitors
  Route::get('visitors', [TrafficController::class, 'index'])->name('visitors.index');
  //Postbacks
  Route::prefix('postbacks')->name('postbacks.')->group(function () {
    Route::get('/', [PostbackController::class, 'index'])->name('index');
    Route::get('/{postbackId}/api-requests', [PostbackController::class, 'getApiRequests'])->name('api-requests');
  });
  //Forms
  Route::prefix('forms')->group(function () {
    Route::get('fields', [FieldController::class, 'index'])->name('fields.index');
  });
  //Whitelist
  Route::get('whitelist', [WhitelistEntryController::class, 'index'])->name('whitelist.index');
});

require __DIR__ . '/settings.php';
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
