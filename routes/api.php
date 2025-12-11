<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\JsonResponse;

use App\Http\Controllers\Api\Offerwall\EventController;
use App\Http\Controllers\OfferwallController;
use App\Http\Controllers\Api\Offerwall\MixController as OfferwallMixController;
use App\Http\Controllers\Api\Form\FieldController as ApiFieldController;
use App\Http\Controllers\Api\LeadController;

Route::any('/health', function () {
  return new JsonResponse(['status' => 'ok']);
});

// Rutas para Offerwall Service
Route::prefix('offerwall')->name('api.offerwall.')->group(function () {
  Route::get('/integrations', [OfferwallController::class, 'getOfferwallIntegrations'])->name('integrations');
  Route::post('/events/conversion', [EventController::class, 'handleOfferwallConversion'])->name('events.conversion');
  Route::post('/mix/{offerwallMix}', [OfferwallMixController::class, 'trigger'])->name('mix.trigger');
});

Route::prefix('fields')->group(function () {
  Route::get('/export', [ApiFieldController::class, 'export'])->name('api.fields.export');
  Route::post('/import', [ApiFieldController::class, 'import'])->name('api.fields.import');
});

Route::get('/leads/{fingerprint}', [LeadController::class, 'getLeadDetails'])->name('api.leads.details');

//Other file routes
require __DIR__ . '/leads.php';
require __DIR__ . '/postback.php';
require __DIR__ . '/bundlers.php';
require __DIR__ . '/webhook.php';
