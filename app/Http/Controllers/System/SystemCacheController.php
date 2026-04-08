<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\CacheRegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemCacheController extends Controller
{
  public function __construct(private CacheRegistryService $registry) {}

  /**
   * Display the cache management page.
   */
  public function index(): Response
  {
    return Inertia::render('system/cache', [
      'entries' => $this->registry->getAll(),
    ]);
  }

  /**
   * Flush a specific cache key.
   */
  public function flush(Request $request): RedirectResponse
  {
    $request->validate([
      'key' => 'required|string',
    ]);

    $key = $request->input('key');
    $entry = $this->registry->getEntry($key);

    if (!$entry) {
      abort(404, 'Cache key not registered.');
    }

    $this->registry->flush($key);

    add_flash_message(type: 'success', message: "Cache '{$entry['label']}' purged successfully.");

    return back();
  }
}
