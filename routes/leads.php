<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\TrafficLogController;

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
