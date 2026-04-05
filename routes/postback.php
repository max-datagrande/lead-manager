<?php

use App\Http\Controllers\Api\MaxconvController;
use App\Http\Controllers\Api\PingPost\PostbackWebhookController;
use App\Http\Controllers\Api\PostbackController;
use App\Http\Controllers\Api\InternalPostbackFireController;
use App\Http\Controllers\Api\PostbackFireController;
use Illuminate\Support\Facades\Route;

Route::prefix('postback')->group(function () {
  // Ejemplo: /v1/postback/conv?clid=123&payout=10.50&offer_id=ABC&currency=USD&vendor=ni
  Route::get('/conv', [PostbackController::class, 'store'])->name('api.postback.store');

  // Ruta para consultar el estado de un postback
  Route::get('/status/{postbackId}', [PostbackController::class, 'status'])
    ->name('api.postback.status')
    ->where('postbackId', '[0-9]+');

  // Ruta para buscar payout de un cliente específico
  Route::post('/search-payout', [PostbackController::class, 'searchPayout'])->name('api.postback.search-payout');

  // Ruta para reconciliar payouts de un día
  Route::post('/reconcile', [PostbackController::class, 'reconcilePayouts'])->name('api.postback.reconcile');

  // NEW POSTBACKS
  // Postback fire endpoint (recibe callbacks de partners externos)
  Route::get('/fire/{uuid}', [PostbackFireController::class, 'fire'])
    ->name('api.postback.fire')
    ->where('uuid', '[0-9a-f-]{36}')
    ->middleware('throttle:60,1');

  // Internal postback fire endpoint (recibe fingerprint + params, guarda en lead y dispara)
  Route::get('/fire/{uuid}/{fingerprint}', [InternalPostbackFireController::class, 'fire'])
    ->name('api.postback.fire-internal')
    ->where('uuid', '[0-9a-f-]{36}')
    ->middleware('throttle:60,1');

  // Consultar estado de una ejecución
  Route::get('/execution/{executionUuid}', [PostbackFireController::class, 'executionStatus'])
    ->name('api.postback.execution-status')
    ->where('executionUuid', '[0-9a-f-]{36}');
});

// Ping-Post postback webhook (no auth — external buyer callback)
Route::post('ping-post/postback/{dispatch}/{integration}', [PostbackWebhookController::class, 'receive'])
  ->whereNumber(['dispatch', 'integration'])
  ->name('api.ping-post.postback');

// Rutas para Maxconv Service
Route::prefix('maxconv')->group(function () {
  // Obtener todas las ofertas
  Route::get('/offers', [MaxconvController::class, 'getOffers'])->name('api.maxconv.offers');

  // Obtener una oferta específica
  Route::get('/offers/{offerId}', [MaxconvController::class, 'getOffer'])
    ->whereNumber('offerId')
    ->name('api.maxconv.offer');

  // Construir URL de oferta con placeholders
  Route::post('/build-offer-url', [MaxconvController::class, 'buildOfferUrl'])->name('api.maxconv.build-offer-url');

  // Validar placeholders
  Route::post('/validate-placeholders', [MaxconvController::class, 'validatePlaceholders'])->name('api.maxconv.validate-placeholders');

  // Preview de datos de postback
  Route::get('/postback/{postbackId}/preview', [MaxconvController::class, 'previewPostbackData'])
    ->whereNumber('postbackId')
    ->name('api.maxconv.postback-preview');
});
