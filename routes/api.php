<?php

use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\Offerwall\EventController;
use App\Http\Controllers\Api\Offerwall\MixController as OfferwallMixController;
use App\Http\Controllers\Api\ProxyController;
use App\Http\Controllers\Api\TrafficLogController;
use App\Http\Controllers\OfferwallController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

// Basic health check to verify API is reachable from orchestrators and services.
Route::any('/health', function () {
  return new JsonResponse(['status' => 'ok']);
});

// Leads and visitor tracking endpoints protected by host authentication.
Route::middleware(['auth.host'])->group(function () {
  Route::prefix('visitor')->group(function () {
    // Receives raw visitor meta data and stores a traffic log entry.
    Route::post('/register', [TrafficLogController::class, 'store'])->name('visitor.register');
  });
  Route::prefix('leads')->group(function () {
    // Registers a new lead coming from publishers.
    Route::post('/register', [LeadController::class, 'store'])->name('api.leads.register');
    // Updates mutable lead attributes such as status or payout.
    Route::post('/update', [LeadController::class, 'update'])->name('api.leads.update');
    // Marks a lead as submitted when qualification is complete.
    Route::post('/submit', [LeadController::class, 'submit'])->name('api.leads.submit');
    // Retrieves the stored lead details by device/browser fingerprint.
    Route::get('/{fingerprint}', [LeadController::class, 'getLeadDetails'])->name('api.leads.details');
  });
  // Forwards Slack payloads through the host-authenticated proxy.
  Route::post('/proxy/slack', [ProxyController::class, 'forward'])
    ->name('api.proxy.slack');
});

// Offerwall management endpoints that run inside the lead manager.
Route::prefix('offerwall')->name('api.offerwall.')->group(function () {
  // Lists enabled offerwall providers and credentials.
  Route::get('/integrations', [OfferwallController::class, 'getOfferwallIntegrations'])->name('integrations');
  // Receives callback events when conversions fire from offerwall networks.
  Route::post('/events/conversion', [EventController::class, 'handleOfferwallConversion'])->name('events.conversion');
  // Triggers a Mix workflow defined for a specific offerwall integration.
  Route::post('/mix/{offerwallMix}', [OfferwallMixController::class, 'trigger'])->name('mix.trigger');
});

// Other file routes
require __DIR__ . '/syncs.php';
require __DIR__ . '/postback.php';
require __DIR__ . '/bundlers.php';
require __DIR__ . '/webhook.php';
