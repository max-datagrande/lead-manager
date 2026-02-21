<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WhitelistEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
  Route::resource('users', UserController::class)->except(['show', 'create', 'edit']);
  Route::resource('whitelist', WhitelistEntryController::class)->except(['show', 'create', 'edit']);
});
