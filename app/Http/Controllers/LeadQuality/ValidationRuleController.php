<?php

namespace App\Http\Controllers\LeadQuality;

use App\Enums\LeadQuality\LeadQualityProviderType;
use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeadQuality\StoreValidationRuleRequest;
use App\Http\Requests\LeadQuality\UpdateValidationRuleRequest;
use App\Models\Integration;
use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationRule;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ValidationRuleController extends Controller
{
  public function index(): Response
  {
    $rules = LeadQualityValidationRule::query()
      ->with(['provider:id,name,type,status,is_enabled', 'buyers:id,name,type'])
      ->latest()
      ->get()
      ->map(
        fn(LeadQualityValidationRule $rule) => [
          'id' => $rule->id,
          'name' => $rule->name,
          'slug' => $rule->slug,
          'validation_type' => $rule->validation_type->value,
          'validation_type_label' => $rule->validation_type->label(),
          'status' => $rule->status->value,
          'is_enabled' => $rule->is_enabled,
          'description' => $rule->description,
          'priority' => $rule->priority,
          'provider' => $rule->provider
            ? [
              'id' => $rule->provider->id,
              'name' => $rule->provider->name,
              'type' => $rule->provider->type->value,
              'is_usable' => $rule->provider->isUsable(),
            ]
            : null,
          'buyers' => $rule->buyers->map(
            fn(Integration $buyer) => [
              'id' => $buyer->id,
              'name' => $buyer->name,
              'type' => $buyer->type,
              'is_enabled' => (bool) $buyer->pivot->is_enabled,
            ],
          ),
          'buyers_count' => $rule->buyers->count(),
          'updated_at' => $rule->updated_at,
        ],
      );

    return Inertia::render('lead-quality/validation-rules/index', [
      'rules' => $rules,
      'validation_types' => ValidationType::toArray(),
      'statuses' => RuleStatus::toArray(),
    ]);
  }

  public function create(): Response
  {
    return Inertia::render('lead-quality/validation-rules/create', [
      'validation_types' => ValidationType::toArray(),
      'statuses' => RuleStatus::toArray(),
      'provider_types' => LeadQualityProviderType::toArray(),
      'providers' => $this->providerOptions(),
      'buyers' => $this->buyerOptions(),
    ]);
  }

  public function store(StoreValidationRuleRequest $request): RedirectResponse
  {
    try {
      $data = $request->validated();
      $buyerIds = $data['buyer_ids'] ?? [];
      unset($data['buyer_ids']);

      $rule = LeadQualityValidationRule::create($data);
      $rule->buyers()->sync($this->buyerSyncPayload($buyerIds));

      add_flash_message(type: 'success', message: 'Validation rule created successfully.');

      return redirect()->route('lead-quality.validation-rules.index');
    } catch (\Throwable $th) {
      add_flash_message(type: 'error', message: "Validation rule not created. Error: {$th->getMessage()}");

      return redirect()->back()->withInput();
    }
  }

  public function edit(LeadQualityValidationRule $validationRule): Response
  {
    $validationRule->load('buyers:id');

    return Inertia::render('lead-quality/validation-rules/edit', [
      'rule' => [
        'id' => $validationRule->id,
        'name' => $validationRule->name,
        'slug' => $validationRule->slug,
        'validation_type' => $validationRule->validation_type->value,
        'provider_id' => $validationRule->provider_id,
        'status' => $validationRule->status->value,
        'is_enabled' => $validationRule->is_enabled,
        'description' => $validationRule->description,
        'settings' => $validationRule->settings ?? [],
        'priority' => $validationRule->priority,
        'buyer_ids' => $validationRule->buyers->pluck('id')->all(),
      ],
      'validation_types' => ValidationType::toArray(),
      'statuses' => RuleStatus::toArray(),
      'provider_types' => LeadQualityProviderType::toArray(),
      'providers' => $this->providerOptions(),
      'buyers' => $this->buyerOptions(),
    ]);
  }

  public function update(UpdateValidationRuleRequest $request, LeadQualityValidationRule $validationRule): RedirectResponse
  {
    try {
      $data = $request->validated();
      $buyerIds = $data['buyer_ids'] ?? [];
      unset($data['buyer_ids']);

      $validationRule->update($data);
      $validationRule->buyers()->sync($this->buyerSyncPayload($buyerIds));

      add_flash_message(type: 'success', message: 'Validation rule updated successfully.');

      return redirect()->route('lead-quality.validation-rules.index');
    } catch (\Throwable $th) {
      add_flash_message(type: 'error', message: "Validation rule not updated. Error: {$th->getMessage()}");

      return redirect()->back()->withInput();
    }
  }

  public function destroy(LeadQualityValidationRule $validationRule): RedirectResponse
  {
    try {
      $validationRule->delete();
      add_flash_message(type: 'success', message: 'Validation rule deleted successfully.');
    } catch (\Throwable $th) {
      add_flash_message(type: 'error', message: "Validation rule not deleted. Error: {$th->getMessage()}");
    }

    return redirect()->back();
  }

  /**
   * @return array<int, array{id: int, name: string, type: string, type_label: string, is_usable: bool}>
   */
  private function providerOptions(): array
  {
    return LeadQualityProvider::query()
      ->orderBy('name')
      ->get(['id', 'name', 'type', 'status', 'is_enabled'])
      ->map(
        fn(LeadQualityProvider $p) => [
          'id' => $p->id,
          'name' => $p->name,
          'type' => $p->type->value,
          'type_label' => $p->type->label(),
          'is_usable' => $p->isUsable(),
        ],
      )
      ->all();
  }

  /**
   * @return array<int, array{id: int, name: string, type: string}>
   */
  private function buyerOptions(): array
  {
    return Integration::query()
      ->whereIn('type', ['ping-post', 'post-only'])
      ->where('is_active', true)
      ->orderBy('name')
      ->get(['id', 'name', 'type'])
      ->map(
        fn(Integration $i) => [
          'id' => $i->id,
          'name' => $i->name,
          'type' => $i->type,
        ],
      )
      ->all();
  }

  /**
   * Builds the sync payload with pivot defaults (`is_enabled = true` for each buyer).
   *
   * @param  array<int, int>  $buyerIds
   * @return array<int, array{is_enabled: bool}>
   */
  private function buyerSyncPayload(array $buyerIds): array
  {
    $payload = [];
    foreach ($buyerIds as $id) {
      $payload[(int) $id] = ['is_enabled' => true];
    }

    return $payload;
  }
}
