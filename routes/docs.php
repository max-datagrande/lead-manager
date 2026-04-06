<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])
  ->prefix('docs')
  ->name('docs.')
  ->group(function () {
    Route::get('catalyst', fn() => redirect()->route('docs.catalyst.overview'));

    Route::prefix('catalyst')
      ->name('catalyst.')
      ->group(function () {
        Route::get('overview', fn() => Inertia::render('docs/catalyst/overview'))->name('overview');
        Route::get('installation', fn() => Inertia::render('docs/catalyst/installation'))->name('installation');
        Route::get('visitor', fn() => Inertia::render('docs/catalyst/visitor'))->name('visitor');
        Route::get('leads', fn() => Inertia::render('docs/catalyst/leads'))->name('leads');
        Route::get('share-leads', fn() => Inertia::render('docs/catalyst/share-leads'))->name('share-leads');
        Route::get('offerwall', fn() => Inertia::render('docs/catalyst/offerwall'))->name('offerwall');
        Route::get('events', fn() => Inertia::render('docs/catalyst/events'))->name('events');
        Route::get('examples', fn() => Inertia::render('docs/catalyst/examples'))->name('examples');
      });
  });
