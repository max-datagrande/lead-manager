<?php

namespace App\Http\Controllers\LeadQuality;

use App\Enums\LeadQuality\LeadQualityProviderType;
use App\Enums\LeadQuality\ProviderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeadQuality\StoreProviderRequest;
use App\Http\Requests\LeadQuality\UpdateProviderRequest;
use App\Models\LeadQualityProvider;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProviderController extends Controller
{
  public function index(): Response
  {
    $providers = LeadQualityProvider::query()->with('creator:id,name')->withCount('validationRules')->latest()->get()->map(
      fn(LeadQualityProvider $p) => [
        'id' => $p->id,
        'name' => $p->name,
        'type' => $p->type->value,
        'type_label' => $p->type->label(),
        'status' => $p->status->value,
        'is_enabled' => $p->is_enabled,
        'environment' => $p->environment,
        'notes' => $p->notes,
        'validation_rules_count' => $p->validation_rules_count,
        'created_at' => $p->created_at,
        'updated_at' => $p->updated_at,
        'creator' => $p->creator?->only(['id', 'name']),
      ],
    );

    return Inertia::render('lead-quality/providers/index', [
      'providers' => $providers,
      'provider_types' => LeadQualityProviderType::toArray(),
      'statuses' => ProviderStatus::toArray(),
    ]);
  }

  public function create(): Response
  {
    return Inertia::render('lead-quality/providers/create', [
      'provider_types' => LeadQualityProviderType::toArray(),
      'statuses' => ProviderStatus::toArray(),
      'environments' => [
        ['value' => 'production', 'label' => 'Production'],
        ['value' => 'sandbox', 'label' => 'Sandbox'],
        ['value' => 'test', 'label' => 'Test'],
      ],
    ]);
  }

  public function store(StoreProviderRequest $request): RedirectResponse
  {
    try {
      $data = $this->foldFriendlyNameIntoSettings($request->validated(), null);
      LeadQualityProvider::create($data);
      add_flash_message(type: 'success', message: 'Provider created successfully.');

      return redirect()->route('lead-quality.providers.index');
    } catch (\Throwable $th) {
      add_flash_message(type: 'error', message: "Provider not created. Error: {$th->getMessage()}");

      return redirect()->back()->withInput();
    }
  }

  public function edit(LeadQualityProvider $provider): Response
  {
    $credentials = $provider->credentials ?? [];

    // Split credentials so the admin never ships plaintext secrets to the browser:
    //   - safe_credentials: non-secret values that are OK to prefill in the form
    //     (e.g. Twilio account_sid, verify_service_sid).
    //   - credential_status: per-key boolean indicating "a value exists in DB",
    //     so the form can show an "Already set" badge on blank secret inputs.
    $secretKeys = ['auth_token', 'api_key', 'secret', 'password', 'token'];
    $safeCredentials = [];
    $credentialStatus = [];
    $credentialLengths = [];
    foreach ($credentials as $key => $value) {
      $isString = is_string($value);
      $hasValue = $isString ? $value !== '' : $value !== null;
      $credentialStatus[$key] = $hasValue;
      if (!in_array(strtolower((string) $key), $secretKeys, true)) {
        $safeCredentials[$key] = $value;
      } elseif ($hasValue && $isString) {
        // Length is shared so the form can render a realistic "filled" placeholder
        // for secret fields (bullets matching the stored size) without exposing
        // the actual value. Twilio auth tokens are always 32 chars, so this is a
        // minor info leak at worst — but it makes the UX "this is populated"
        // affordance immediately obvious.
        $credentialLengths[$key] = strlen($value);
      }
    }

    return Inertia::render('lead-quality/providers/edit', [
      'provider' => [
        'id' => $provider->id,
        'name' => $provider->name,
        'type' => $provider->type->value,
        'status' => $provider->status->value,
        'is_enabled' => $provider->is_enabled,
        'environment' => $provider->environment,
        'safe_credentials' => $safeCredentials,
        'credential_status' => $credentialStatus,
        'credential_lengths' => $credentialLengths,
        'settings' => $provider->settings ?? [],
        // Surface friendly_name as a top-level field for a flat form. It lives
        // inside settings.friendly_name in DB but the form doesn't need to
        // know that — the controller moves it back in on update.
        'friendly_name' => data_get($provider->settings, 'friendly_name', ''),
        'notes' => $provider->notes,
      ],
      'provider_types' => LeadQualityProviderType::toArray(),
      'statuses' => ProviderStatus::toArray(),
      'environments' => [
        ['value' => 'production', 'label' => 'Production'],
        ['value' => 'sandbox', 'label' => 'Sandbox'],
        ['value' => 'test', 'label' => 'Test'],
      ],
    ]);
  }

  public function update(UpdateProviderRequest $request, LeadQualityProvider $provider): RedirectResponse
  {
    try {
      $data = $request->validated();
      // Merge incoming credentials with existing ones so the user can blank out a single field
      // without nuking the rest. Empty strings are preserved; only totally missing keys fall back.
      $existing = $provider->credentials ?? [];
      // Drop blank strings so the "leave empty to keep current" contract holds
      // end-to-end. The edit form locks populated secret fields behind a trash
      // button; if the admin unlocks one but saves without typing a new value,
      // we must NOT overwrite the stored secret with an empty string.
      $incoming = array_filter($data['credentials'] ?? [], fn($v) => $v !== '' && $v !== null);
      $data['credentials'] = array_replace($existing, $incoming);

      // friendly_name gets folded into settings.friendly_name. Detect whether
      // it actually changed so we only hit Twilio when there's something to sync.
      $previousFriendlyName = data_get($provider->settings, 'friendly_name');
      $data = $this->foldFriendlyNameIntoSettings($data, $provider);
      $newFriendlyName = data_get($data, 'settings.friendly_name');

      $provider->update($data);

      $warning = null;
      if (
        $provider->type->value === 'twilio_verify' &&
        is_string($newFriendlyName) &&
        $newFriendlyName !== '' &&
        $newFriendlyName !== $previousFriendlyName
      ) {
        try {
          app(\App\Services\LeadQuality\Providers\TwilioVerifyProvider::class)->syncFriendlyName($provider, $newFriendlyName);
        } catch (\Throwable $e) {
          // Local save already persisted — surface the Twilio hiccup as a
          // warning instead of rolling back; the admin can retry later.
          $warning = "Provider saved locally but Twilio friendly-name sync failed: {$e->getMessage()}";
        }
      }

      $message = $warning ?? 'Provider updated successfully.';
      add_flash_message(type: $warning ? 'warning' : 'success', message: $message);

      return redirect()->route('lead-quality.providers.index');
    } catch (\Throwable $th) {
      add_flash_message(type: 'error', message: "Provider not updated. Error: {$th->getMessage()}");

      return redirect()->back()->withInput();
    }
  }

  public function destroy(LeadQualityProvider $provider): RedirectResponse
  {
    try {
      $provider->delete();
      add_flash_message(type: 'success', message: 'Provider deleted successfully.');
    } catch (\Throwable $th) {
      add_flash_message(type: 'error', message: "Provider not deleted. Error: {$th->getMessage()}");
    }

    return redirect()->back();
  }

  /**
   * Moves the flat `friendly_name` input into `settings.friendly_name`, keeping
   * the rest of settings intact. Returns the data array with `friendly_name`
   * removed from the top level (the column doesn't exist) and merged into settings.
   *
   * @param  array<string, mixed>  $data
   * @return array<string, mixed>
   */
  private function foldFriendlyNameIntoSettings(array $data, ?LeadQualityProvider $provider): array
  {
    if (!array_key_exists('friendly_name', $data)) {
      return $data;
    }

    $settings = $data['settings'] ?? ($provider?->settings ?? []);
    if ($data['friendly_name'] === null || $data['friendly_name'] === '') {
      unset($settings['friendly_name']);
    } else {
      $settings['friendly_name'] = (string) $data['friendly_name'];
    }

    $data['settings'] = $settings;
    unset($data['friendly_name']);

    return $data;
  }
}
