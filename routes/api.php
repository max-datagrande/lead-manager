<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\TrafficLogController;
use App\Http\Controllers\Api\PostbackController;
use App\Http\Controllers\Api\GeolocationController;
use App\Http\Controllers\Api\MaxconvController;
use App\Http\Controllers\Api\Offerwall\EventController;

Route::any('/health', function () {
  return new JsonResponse(['status' => 'ok']);
});
Route::middleware(['auth.host'])->group(function () {
  Route::prefix('visitor')->group(function () {
    Route::post('/register', [TrafficLogController::class, 'store'])->name('visitor.register');
  });

  Route::prefix('leads')->group(function () {
    Route::post('/register', [LeadController::class, 'store'])->name('api.leads.register');
    Route::post('/update', [LeadController::class, 'update'])->name('api.leads.update');
    Route::post('/submit', [LeadController::class, 'submit'])->name('api.leads.submit');
  });
});

Route::prefix('postback')->group(function () {
  // Ejemplo: /v1/postback/conv?clid=123&payout=10.50&offer_id=ABC&currency=USD&vendor=ni
  Route::get('/conv', [PostbackController::class, 'store'])
    ->name('api.postback.store');

  // Ruta para consultar el estado de un postback
  Route::get('/status/{postbackId}', [PostbackController::class, 'status'])
    ->name('api.postback.status')
    ->where('postbackId', '[0-9]+');

  // Ruta para buscar payout de un cliente específico
  Route::post('/search-payout', [PostbackController::class, 'searchPayout'])
    ->name('api.postback.search-payout');

  // Ruta para reconciliar payouts de un día
  Route::post('/reconcile', [PostbackController::class, 'reconcilePayouts'])
    ->name('api.postback.reconcile');

  // Ruta para obtener reportes de NI (admin)
  Route::get('/report', [PostbackController::class, 'getReport'])
    ->name('api.postback.report');
});

// Rutas de Geolocalización - Protegidas por whitelist de dominios
Route::middleware(['domain.whitelist'])->prefix('geolocation')->group(function () {
  // Endpoint principal para obtener geolocalización por IP
  Route::post('/lookup', [GeolocationController::class, 'getLocationByIp'])
    ->name('api.geolocation.lookup');

  // Endpoint para verificar el estado de la API
  Route::get('/status', [GeolocationController::class, 'status'])
    ->name('api.geolocation.status');
});

// Rutas para Maxconv Service
Route::prefix('maxconv')->group(function () {
  // Obtener todas las ofertas
  Route::get('/offers', [MaxconvController::class, 'getOffers'])
    ->name('api.maxconv.offers');

  // Obtener una oferta específica
  Route::get('/offers/{offerId}', [MaxconvController::class, 'getOffer'])
    ->name('api.maxconv.offer');

  // Construir URL de oferta con placeholders
  Route::post('/build-offer-url', [MaxconvController::class, 'buildOfferUrl'])
    ->name('api.maxconv.build-offer-url');

  // Validar placeholders
  Route::post('/validate-placeholders', [MaxconvController::class, 'validatePlaceholders'])
    ->name('api.maxconv.validate-placeholders');

  // Preview de datos de postback
  Route::get('/postback/{postbackId}/preview', [MaxconvController::class, 'previewPostbackData'])
    ->name('api.maxconv.postback-preview');
});

Route::prefix('offerwall')->group(function () {
    Route::post('/events/conversion', [EventController::class, 'handleOfferwallConversion'])
        ->name('api.offerwall.events.conversion');
});
