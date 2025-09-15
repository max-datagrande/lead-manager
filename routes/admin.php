<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WhitelistEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', UserController::class);

    // Rutas del módulo Whitelist
    Route::resource('whitelist', WhitelistEntryController::class)->except(['show', 'create', 'edit']);
});
