<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\JsonResponse;

use App\Http\Controllers\Api\Offerwall\EventController;
use App\Http\Controllers\OfferwallController;
use App\Http\Controllers\Api\Offerwall\MixController as OfferwallMixController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\TrafficLogController;

Route::any('/health', function () {
  return new JsonResponse(['status' => 'ok']);
});

//Leads routes
Route::middleware(['auth.host'])->group(function () {
  Route::prefix('visitor')->group(function () {
    Route::post('/register', [TrafficLogController::class, 'store'])->name('visitor.register');
  });
  Route::prefix('leads')->group(function () {
    Route::post('/register', [LeadController::class, 'store'])->name('api.leads.register');
    Route::post('/update', [LeadController::class, 'update'])->name('api.leads.update');
    Route::post('/submit', [LeadController::class, 'submit'])->name('api.leads.submit');
    Route::get('/{fingerprint}', [LeadController::class, 'getLeadDetails'])->name('api.leads.details');
  });
});


//Offerwall routes
Route::prefix('offerwall')->name('api.offerwall.')->group(function () {
  Route::get('/integrations', [OfferwallController::class, 'getOfferwallIntegrations'])->name('integrations');
  Route::post('/events/conversion', [EventController::class, 'handleOfferwallConversion'])->name('events.conversion');
  Route::post('/mix/{offerwallMix}', [OfferwallMixController::class, 'trigger'])->name('mix.trigger');
});




//Other file routes
require __DIR__ . '/syncs.php';
require __DIR__ . '/postback.php';
require __DIR__ . '/bundlers.php';
require __DIR__ . '/webhook.php';
