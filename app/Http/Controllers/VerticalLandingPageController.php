<?php

namespace App\Http\Controllers;

use App\Models\VerticalLandingPage;
use App\Models\Vertical;
use App\Models\Company;
use App\Http\Requests\VerticalLandingPages\StoreRequest;
use App\Http\Requests\VerticalLandingPages\UpdateRequest;
use Inertia\Inertia;

class VerticalLandingPageController extends Controller
{
    public function index()
    {
        $landingPages = VerticalLandingPage::with(['vertical', 'company'])->latest()->get();

        return Inertia::render('verticalLandingPages/Index', [
            'landingPages' => $landingPages,
            'verticals'    => Vertical::where('active', true)->get(['id', 'name']),
            'companies'    => Company::all(['id', 'name']),
        ]);
    }

    public function create()
    {
        return Inertia::render('verticalLandingPages/Create', [
            'verticals' => Vertical::where('active', true)->get(['id', 'name']),
            'companies' => Company::all(['id', 'name']),
        ]);
    }

    public function store(StoreRequest $request)
    {
        VerticalLandingPage::create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('vertical_landing_pages.index')
            ->with('success', 'Landing page created successfully.');
    }

    public function edit(VerticalLandingPage $verticalLandingPage)
    {
        return Inertia::render('verticalLandingPages/Edit', [
            'landingPage' => $verticalLandingPage,
            'verticals'   => Vertical::where('active', true)->get(['id', 'name']),
            'companies'   => Company::all(['id', 'name']),
        ]);
    }

    public function update(UpdateRequest $request, VerticalLandingPage $verticalLandingPage)
    {
        $verticalLandingPage->update([
            ...$request->validated(),
            'updated_user_id' => auth()->id(),
        ]);

        return redirect()->route('vertical_landing_pages.index')
            ->with('success', 'Landing page updated successfully.');
    }

    public function destroy(VerticalLandingPage $verticalLandingPage)
    {
        $verticalLandingPage->delete();

        return redirect()->route('vertical_landing_pages.index')
            ->with('success', 'Landing page deleted successfully.');
    }
}