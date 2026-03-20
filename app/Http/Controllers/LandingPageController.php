<?php

namespace App\Http\Controllers;

use App\Models\LandingPage;
use App\Models\Vertical;
use App\Models\Company;
use App\Http\Requests\LandingPages\StoreRequest;
use App\Http\Requests\LandingPages\UpdateRequest;
use Inertia\Inertia;

class LandingPageController extends Controller
{
  public function index()
  {
    $landingPages = LandingPage::with(['vertical', 'company'])->latest()->get();

    return Inertia::render('landings/index', [
      'landingPages' => $landingPages,
      'verticals'    => Vertical::where('active', true)->get(['id', 'name']),
      'companies'    => Company::all(['id', 'name']),
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
    LandingPage::create($request->validated());

    return redirect()->route('landing_pages.index')
      ->with('success', 'Landing page created successfully.');
  }

  public function edit(LandingPage $landingPage)
  {
    return Inertia::render('landings/edit', [
      'landingPage' => $landingPage,
      'verticals'   => Vertical::where('active', true)->get(['id', 'name']),
      'companies'   => Company::all(['id', 'name']),
    ]);
  }

  public function update(UpdateRequest $request, LandingPage $landingPage)
  {
    try {
      $data = $request->validated();
      $landingPage->update($data);
      return redirect()->route('landing_pages.index')
        ->with('success', 'Landing page updated successfully.');
    } catch (\Throwable $th) {
      $error = $th->getMessage();
      return redirect()->back()
        ->with('error', $error);
    }
  }

  public function destroy(LandingPage $landingPage)
  {
    $landingPage->delete();
    return redirect()->route('landing_pages.index')
      ->with('success', 'Landing page deleted successfully.');
  }
}
