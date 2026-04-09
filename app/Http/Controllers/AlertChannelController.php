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
    $channels = AlertChannel::with('creator')
      ->latest()
      ->get()
      ->map(function (AlertChannel $channel) {
        $channel->webhook_url_masked = $this->maskUrl($channel->webhook_url);
        return $channel;
      });

    return Inertia::render('alert-channels/index', [
      'alert_channels' => $channels,
      'channel_types' => $resolver->availableTypes(),
    ]);
  }

  private function maskUrl(?string $url): string
  {
    if (!$url) {
      return '';
    }

    $length = strlen($url);
    if ($length <= 20) {
      return str_repeat('•', $length);
    }

    return substr($url, 0, 12) . str_repeat('•', min($length - 16, 30)) . substr($url, -4);
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
    if (empty($data['webhook_url'])) {
      unset($data['webhook_url']);
    }
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
