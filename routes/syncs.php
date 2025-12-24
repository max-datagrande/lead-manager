<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Form\FieldController as ApiFieldController;

//Sync routes
Route::prefix('fields')->group(function () {
  Route::get('/export', [ApiFieldController::class, 'export'])->name('api.fields.export');
  Route::post('/import', [ApiFieldController::class, 'import'])->name('api.fields.import');
});
