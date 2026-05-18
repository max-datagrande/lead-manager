<?php

namespace App\Http\Controllers;

use App\Http\Requests\LandingPages\StoreRequest;
use App\Http\Requests\LandingPages\UpdateRequest;
use App\Models\Company;
use App\Models\LandingPage;
use App\Models\Vertical;
use App\Services\InternalTokenResolverService;
use Inertia\Inertia;

class LandingPageController extends Controller
{
  public function __construct(private readonly InternalTokenResolverService $tokenResolver) {}

  public function index()
  {
    $landingPages = LandingPage::with(['vertical', 'company', 'columns'])
      ->latest()
      ->get();

    return Inertia::render('landings/index', [
      'landingPages' => $landingPages,
      'verticals' => Vertical::where('active', true)->get(['id', 'name']),
      'companies' => Company::all(['id', 'name']),
      'available_columns' => $this->availableColumns(),
    ]);
  }

  public function create()
  {
    return Inertia::render('landings/create', [
      'verticals' => Vertical::where('active', true)->get(['id', 'name']),
      'companies' => Company::all(['id', 'name']),
    ]);
  }

  public function store(StoreRequest $request)
  {
    $data = $request->safe()->except('columns');
    $columns = $request->input('columns', []);

    $landingPage = LandingPage::create($data);
    $this->syncColumns($landingPage, $columns);

    return redirect()->route('landing_pages.index')->with('success', 'Landing page created successfully.');
  }

  public function edit(LandingPage $landingPage)
  {
    return Inertia::render('landings/edit', [
      'landingPage' => $landingPage,
      'verticals' => Vertical::where('active', true)->get(['id', 'name']),
      'companies' => Company::all(['id', 'name']),
    ]);
  }

  public function update(UpdateRequest $request, LandingPage $landingPage)
  {
    try {
      $data = $request->safe()->except('columns');
      $columns = $request->input('columns', []);

      $landingPage->update($data);
      $this->syncColumns($landingPage, $columns);

      return redirect()->route('landing_pages.index')->with('success', 'Landing page updated successfully.');
    } catch (\Throwable $th) {
      $error = $th->getMessage();
      return redirect()->back()->with('error', $error);
    }
  }

  public function destroy(LandingPage $landingPage)
  {
    $landingPage->delete();
    return redirect()->route('landing_pages.index')->with('success', 'Landing page deleted successfully.');
  }

  private function availableColumns(): array
  {
    $tokens = $this->tokenResolver->getAvailableTokens();

    return [
      'fields' => $tokens['fields'],
      'traffic' => collect($tokens['traffic'])
        ->map(
          fn($token) => [
            'id' => $token['id'],
            'name' => str_replace('traffic.', '', $token['name']),
            'label' => $token['label'],
            'group' => $token['group'],
          ],
        )
        ->values()
        ->all(),
    ];
  }

  private function syncColumns(LandingPage $landingPage, array $columns): void
  {
    $landingPage->columns()->delete();

    if (empty($columns)) {
      return;
    }

    $rows = collect($columns)
      ->map(
        fn($col) => [
          'source' => $col['source'],
          'reference' => (string) $col['reference'],
        ],
      )
      ->unique(fn($col) => $col['source'] . ':' . $col['reference'])
      ->values()
      ->all();

    $landingPage->columns()->createMany($rows);
  }
}
