<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PostbackController;
use App\Http\Controllers\Api\MaxconvController;


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
