<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\TrafficLogController;
use App\Http\Controllers\Api\PostbackController;

Route::any('/health', function () {
  return new JsonResponse(['status' => 'ok']);
});
Route::middleware(['auth.host'])->group(function () {
  Route::prefix('visitor')->group(function () {
    Route::post('/register', [TrafficLogController::class, 'store'])->name('visitor.register');
  });

  Route::prefix('leads')->group(function () {
    Route::post('/register', [LeadController::class, 'store'])->name('leads.register');
    Route::post('/update', [LeadController::class, 'update'])->name('leads.update');
    Route::post('/submit', [LeadController::class, 'store'])->name('leads.submit');
  });
});

Route::prefix('postback')->group(function () {
  // Ejemplo: /v1/postback/conv?clid=123&payout=10.50&offer_id=ABC&currency=USD&vendor=ni
  Route::any('/conv', [PostbackController::class, 'store'])
    ->name('api.postback.store');

  // Ruta para consultar el estado de un postback
  Route::get('/status/{postbackId}', [PostbackController::class, 'status'])
    ->name('api.postback.status')
    ->where('postbackId', '[0-9]+');

  // Ruta para obtener reportes de NI (admin)
  Route::get('/report', [PostbackController::class, 'getReport'])
    ->name('api.postback.report');
});
