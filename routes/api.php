<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\TrafficLogController;

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

Route::get('/user', function (Request $request) {
  return $request->user();
})->middleware('auth:sanctum');


/*
// Natural Intelligence Postback Routes
Route::prefix('v1/postback')->group(function () {
  Route::post('/ni', [\App\Http\Controllers\Api\NaturalIntelligenceController::class, 'postback'])
    ->name('api.ni.postback');
});

// Optional: Admin routes for NI reports (protected)
Route::middleware(['auth:sanctum'])->prefix('v1/admin/ni')->group(function () {
  Route::get('/report', [\App\Http\Controllers\Api\NaturalIntelligenceController::class, 'getReport'])
    ->name('api.ni.report');
}); */
