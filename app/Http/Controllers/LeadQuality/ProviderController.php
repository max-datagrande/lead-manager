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
      LeadQualityProvider::create($request->validated());
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

    return Inertia::render('lead-quality/providers/edit', [
      'provider' => [
        'id' => $provider->id,
        'name' => $provider->name,
        'type' => $provider->type->value,
        'status' => $provider->status->value,
        'is_enabled' => $provider->is_enabled,
        'environment' => $provider->environment,
        'credentials' => $credentials,
        'settings' => $provider->settings ?? [],
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
      $incoming = $data['credentials'] ?? [];
      $data['credentials'] = array_replace($existing, $incoming);

      $provider->update($data);
      add_flash_message(type: 'success', message: 'Provider updated successfully.');

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
}
