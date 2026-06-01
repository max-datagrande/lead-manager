<?php

use App\Http\Controllers\Admin\MappingFindingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WhitelistEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])
  ->prefix('admin')
  ->name('admin.')
  ->group(function () {
    Route::resource('users', UserController::class)
      ->except(['show', 'create', 'edit'])
      ->whereNumber('user');
    Route::post('users/{user}/password-reset', [UserController::class, 'sendPasswordReset'])
      ->name('users.password-reset')
      ->middleware('throttle:6,1')
      ->whereNumber('user');
    Route::resource('whitelist', WhitelistEntryController::class)
      ->except(['show', 'create', 'edit'])
      ->whereNumber('whitelist');

    Route::get('mapping-findings', [MappingFindingController::class, 'index'])->name('mapping-findings.index');
    Route::patch('mapping-findings/{mappingFinding}', [MappingFindingController::class, 'update'])
      ->name('mapping-findings.update')
      ->whereNumber('mappingFinding');
  });
