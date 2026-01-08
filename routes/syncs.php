<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Form\FieldController as ApiFieldController;
use App\Http\Controllers\Api\IntegrationController as ApiIntegrationController;


//Sync routes
Route::prefix('fields')->group(function () {
  Route::get('/export', [ApiFieldController::class, 'export'])->name('api.fields.export');
  Route::post('/import', [ApiFieldController::class, 'import'])->name('api.fields.import');
});

Route::prefix('integrations')->group(function () {
  Route::get('/export', [ApiIntegrationController::class, 'export'])->name('api.integrations.export');
  Route::post('/import', [ApiIntegrationController::class, 'import'])->name('api.integrations.import');
});
