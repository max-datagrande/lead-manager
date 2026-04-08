<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAlertChannelRequest;
use App\Http\Requests\UpdateAlertChannelRequest;
use App\Models\AlertChannel;
use App\Services\Alerts\AlertChannelResolver;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AlertChannelController extends Controller
{
  public function index(AlertChannelResolver $resolver): Response
  {
    $channels = AlertChannel::with('creator')->latest()->get();

    return Inertia::render('alert-channels/index', [
      'alert_channels' => $channels,
      'channel_types' => $resolver->availableTypes(),
    ]);
  }

  public function store(StoreAlertChannelRequest $request): RedirectResponse
  {
    $data = $request->validated();
    try {
      AlertChannel::create($data);
    } catch (\Exception $e) {
      add_flash_message(type: 'error', message: $e->getMessage());
      return redirect()->back();
    }

    add_flash_message(type: 'success', message: 'Alert channel created successfully.');
    return redirect()->back();
  }

  public function update(UpdateAlertChannelRequest $request, AlertChannel $alertChannel): RedirectResponse
  {
    $data = $request->validated();
    try {
      $alertChannel->update($data);
    } catch (\Exception $e) {
      add_flash_message(type: 'error', message: $e->getMessage());
      return redirect()->back();
    }

    add_flash_message(type: 'success', message: 'Alert channel updated successfully.');
    return redirect()->back();
  }

  public function destroy(AlertChannel $alertChannel): RedirectResponse
  {
    try {
      $alertChannel->delete();
    } catch (\Exception $e) {
      add_flash_message(type: 'error', message: $e->getMessage());
      return redirect()->back();
    }

    add_flash_message(type: 'success', message: 'Alert channel deleted successfully.');
    return redirect()->back();
  }
}
