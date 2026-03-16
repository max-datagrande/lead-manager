<?php

namespace App\Http\Controllers;

use App\Enums\FireMode;
use App\Http\Requests\StorePostbackRequest;
use App\Http\Requests\UpdatePostbackRequest;
use App\Models\Platform;
use App\Models\Postback;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PostbackController extends Controller
{
  public function index(): Response
  {
    $rows = Postback::with('platform')->latest()->get();

    return Inertia::render('postbacks/index', [
      'rows' => $rows,
    ]);
  }

  public function create(): Response
  {
    $platforms = Platform::orderBy('name')->get(['id', 'name', 'token_mappings']);

    return Inertia::render('postbacks/create', [
      'platforms' => $platforms,
      'fireModes' => FireMode::toArray(),
      'domains' => [
        ['value' => 'public', 'label' => 'Public', 'url' => config('app.api_url')],
        ['value' => 'internal', 'label' => 'Internal', 'url' => config('app.url')],
      ],
    ]);
  }

  public function store(StorePostbackRequest $request): RedirectResponse
  {
    $data = $request->validated();
    try {
      Postback::create($data);
      add_flash_message(type: 'success', message: 'Postback created successfully.');

      return redirect()->route('postbacks.index');
    } catch (\Throwable $th) {
      add_flash_message(type: 'error', message: 'Postback not created.');

      return redirect()->back();
    }
  }

  public function edit(Postback $postback): Response
  {
    $platforms = Platform::orderBy('name')->get(['id', 'name', 'token_mappings']);

    return Inertia::render('postbacks/edit', [
      'postback' => $postback->load('platform'),
      'platforms' => $platforms,
      'fireModes' => FireMode::toArray(),
      'domains' => [
        ['value' => 'public', 'label' => 'Public', 'url' => config('app.api_url')],
        ['value' => 'internal', 'label' => 'Internal', 'url' => config('app.url')],
      ],
    ]);
  }

  public function update(UpdatePostbackRequest $request, Postback $postback): RedirectResponse
  {
    $data = $request->validated();
    try {
      $postback->update($data);
      add_flash_message(type: 'success', message: 'Postback updated successfully.');

      return redirect()->route('postbacks.index');
    } catch (\Throwable $th) {
      $error = $th->getMessage();
      add_flash_message(type: 'error', message: "Postback not updated. Error: $error");
      return redirect()->back();
    }
  }

  public function destroy(Postback $postback): RedirectResponse
  {
    try {
      $postback->delete();
      add_flash_message(type: 'success', message: 'Postback deleted successfully.');
    } catch (\Throwable $th) {
      add_flash_message(type: 'error', message: 'Postback not deleted.');
    }

    return redirect()->back();
  }
}
