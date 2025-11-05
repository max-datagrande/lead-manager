<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Webhooks\LeadController as WebhookLeadController;
use App\Http\Middleware\AuthenticateApiKey;

Route::prefix('webhook')->middleware(AuthenticateApiKey::class)->group(function () {
  Route::prefix('leads')->group(function () {
    Route::post('/store', [WebhookLeadController::class, 'store'])->name('api.leads.store');
  });
});
