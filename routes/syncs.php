<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Form\FieldController as ApiFieldController;
use App\Http\Controllers\Api\IntegrationController as ApiIntegrationController;
use App\Http\Controllers\Api\CompanyController as ApiCompanyController;
use App\Http\Controllers\Api\VerticalController as ApiVerticalController;


//Sync routes
Route::prefix('fields')->group(function () {
  Route::get('/export', [ApiFieldController::class, 'export'])->name('api.fields.export');
  Route::post('/import', [ApiFieldController::class, 'import'])->name('api.fields.import');
});

Route::prefix('integrations')->group(function () {
  Route::get('/export', [ApiIntegrationController::class, 'export'])->name('api.integrations.export');
  Route::post('/import', [ApiIntegrationController::class, 'import'])->name('api.integrations.import');
});

Route::prefix('companies')->group(function () {
  Route::get('/export', [ApiCompanyController::class, 'export'])->name('api.companies.export');
  Route::post('/import', [ApiCompanyController::class, 'import'])->name('api.companies.import');
});

Route::prefix('verticals')->group(function () {
  Route::get('/export', [ApiVerticalController::class, 'export'])->name('api.verticals.export');
  Route::post('/import', [ApiVerticalController::class, 'import'])->name('api.verticals.import');
});
