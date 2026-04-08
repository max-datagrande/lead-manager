<?php

use App\Http\Controllers\System\SystemCacheController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])
  ->prefix('system')
  ->name('system.')
  ->group(function () {
    Route::get('cache', [SystemCacheController::class, 'index'])->name('cache.index');
    Route::post('cache/flush', [SystemCacheController::class, 'flush'])->name('cache.flush');
  });
