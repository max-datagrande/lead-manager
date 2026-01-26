<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GeolocationController;

// Rutas de Geolocalización - Protegidas por whitelist de dominios
Route::middleware(['domain.whitelist'])->prefix('geolocation')->group(function () {
  // Endpoint principal para obtener geolocalización por IP
  Route::post('/lookup', [GeolocationController::class, 'getLocationByIp'])
    ->name('api.geolocation.lookup');

  // Endpoint para verificar el estado de la API
  Route::get('/status', [GeolocationController::class, 'status'])
    ->name('api.geolocation.status');
});
