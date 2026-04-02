<?php

namespace App\Http\Controllers\PingPost;

use App\Enums\PricingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\PingPost\StoreBuyerConfigRequest;
use App\Models\Buyer;
use App\Models\Integration;
use App\Services\PingPost\BuyerConfigService;
use Inertia\Inertia;
use Inertia\Response;

class BuyerController extends Controller
{
  public function __construct(private readonly BuyerConfigService $configService) {}

  public function index(): Response
  {
    $buyers = Buyer::with(['integration', 'buyerConfig', 'company'])
      ->latest()
      ->paginate(25);

    return Inertia::render('ping-post/buyers/index', [
      'buyers' => $buyers,
    ]);
  }

  public function create(): Response|\Illuminate\Http\RedirectResponse
  {
    $integrations = Integration::whereIn('type', ['ping-post', 'post-only'])
      ->orderBy('name')
      ->get(['id', 'name', 'type']);

    if ($integrations->isEmpty()) {
      return redirect()
        ->route('ping-post.buyers.index')
        ->with('error', 'No hay integraciones ping-post o post-only disponibles. Crea una integración primero e inténtalo nuevamente.');
    }

    return Inertia::render('ping-post/buyers/create', [
      'integrations' => $integrations,
      'pricingTypes' => PricingType::toArray(),
    ]);
  }

  public function store(StoreBuyerConfigRequest $request): \Illuminate\Http\RedirectResponse
  {
    $validated = $request->validated();

    $buyer = Buyer::create([
      'name' => $validated['name'],
      'integration_id' => $validated['integration_id'],
      'company_id' => $validated['company_id'] ?? null,
      'is_active' => $validated['is_active'] ?? true,
    ]);

    $configData = $this->extractConfigData($validated);
    $this->configService->createConfig($buyer->integration, $configData);

    if ($request->has('eligibility_rules')) {
      $this->configService->syncEligibilityRules($buyer->integration, $request->input('eligibility_rules', []));
    }

    if ($request->has('caps')) {
      $this->configService->syncCapRules($buyer->integration, $request->input('caps', []));
    }

    return redirect()->route('ping-post.buyers.index')->with('success', 'Buyer created successfully.');
  }

  public function show(Buyer $buyer): Response
  {
    $buyer->load(['integration.environments', 'buyerConfig', 'eligibilityRules', 'capRules', 'company']);

    return Inertia::render('ping-post/buyers/show', [
      'buyer' => $buyer,
    ]);
  }

  public function edit(Buyer $buyer): Response
  {
    $buyer->load(['integration', 'buyerConfig', 'eligibilityRules', 'capRules']);

    return Inertia::render('ping-post/buyers/edit', [
      'buyer' => $buyer,
      'integrations' => Integration::whereIn('type', ['ping-post', 'post-only'])
        ->orderBy('name')
        ->get(['id', 'name', 'type']),
      'pricingTypes' => PricingType::toArray(),
    ]);
  }

  public function update(StoreBuyerConfigRequest $request, Buyer $buyer): \Illuminate\Http\RedirectResponse
  {
    $validated = $request->validated();

    $buyer->update([
      'name' => $validated['name'],
      'company_id' => $validated['company_id'] ?? null,
      'is_active' => $validated['is_active'] ?? true,
    ]);

    $configData = $this->extractConfigData($validated);
    $config = $buyer->buyerConfig;

    if ($config) {
      $this->configService->updateConfig($config, $configData);
    } else {
      $this->configService->createConfig($buyer->integration, $configData);
    }

    if ($request->has('eligibility_rules')) {
      $this->configService->syncEligibilityRules($buyer->integration, $request->input('eligibility_rules', []));
    }

    if ($request->has('caps')) {
      $this->configService->syncCapRules($buyer->integration, $request->input('caps', []));
    }

    return redirect()->route('ping-post.buyers.show', $buyer)->with('success', 'Buyer updated successfully.');
  }

  public function duplicate(Buyer $buyer): \Illuminate\Http\RedirectResponse
  {
    $buyer->load(['buyerConfig', 'eligibilityRules', 'capRules']);

    $clone = Buyer::create([
      'name' => $buyer->name . ' (Copy)',
      'integration_id' => null, // no integration yet — user must select one
      'company_id' => $buyer->company_id,
      'is_active' => false,
    ]);

    // Note: no integration assigned yet; config/rules are tied to integration.
    // User should assign an integration on the edit page before the buyer is usable.

    return redirect()->route('ping-post.buyers.edit', $clone)->with('success', 'Buyer duplicated — assign an integration before activating.');
  }

  public function destroy(Buyer $buyer): \Illuminate\Http\RedirectResponse
  {
    $buyer->delete();

    return redirect()->route('ping-post.buyers.index')->with('success', 'Buyer deleted successfully.');
  }

  /**
   * Strip buyer-level fields from validated data to get only BuyerConfig fields.
   */
  private function extractConfigData(array $validated): array
  {
    return array_diff_key($validated, array_flip(['name', 'integration_id', 'company_id', 'is_active']));
  }
}
