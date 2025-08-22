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
  Route::get('/conv', [PostbackController::class, 'store'])
    ->name('api.postback.store');
});
