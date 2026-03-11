<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlatformRequest;
use App\Http\Requests\UpdatePlatformRequest;
use App\Models\Company;
use App\Models\Platform;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlatformController extends Controller
{
  public function index(): Response
  {
    $platforms = Platform::with(['company', 'creator'])
      ->latest()
      ->get();

    $companies = Company::orderBy('name')->get(['id', 'name']);

    $props = [
      'platforms' => $platforms,
      'companies' => $companies,
    ];

    return Inertia::render('platforms/index', $props);
  }

  public function store(StorePlatformRequest $request): RedirectResponse
  {
    $data = $request->validated();
    try {
      Platform::create($data);
    } catch (\Exception $e) {
      add_flash_message(type: 'error', message: $e->getMessage());
      return redirect()->back();
    }

    add_flash_message(type: 'success', message: 'Platform created successfully.');
    return redirect()->back();
  }

  public function update(UpdatePlatformRequest $request, Platform $platform): RedirectResponse
  {
    $data = $request->validated();
    try {
      $platform->update($data);
    } catch (\Exception $e) {
      add_flash_message(type: 'error', message: $e->getMessage());
      return redirect()->back();
    }
    add_flash_message(type: 'success', message: 'Platform updated successfully.');
    return redirect()->back();
  }

  public function destroy(Platform $platform): RedirectResponse
  {
    try {
      $platform->delete();
    } catch (\Exception $e) {
      add_flash_message(type: 'error', message: $e->getMessage());
      return redirect()->back();
    }

    add_flash_message(type: 'success', message: 'Platform deleted successfully.');
    return redirect()->back();
  }
}
