<?php

namespace App\Http\Controllers;

use App\Models\LandingPage;
use App\Models\LandingPageVersion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LandingVersionController extends Controller
{
    // List versions for a landing page
    public function index(LandingPage $landing_page)
    {
        $versions = $landing_page->versions()
            ->latest()
            ->get()
            ->map(function ($version) use ($landing_page) {
                return [
                    ...$version->toArray(),
                    'fullUrl' => rtrim($landing_page->url, '/') . $version->url,
                ];
            });

        return Inertia::render('landings/versions/index', [
            'landingPage' => $landing_page,
            'versions' => $versions,
        ]);
    }

    // Store a new version
    public function store(Request $request, LandingPage $landing_page)
    {
        $request->validate([
            'name' => 'required|string|max:50'
        ]);

        $landing_page->versions()->create([
            'name' => $request->name,
            'description' => $request->description,
            'url' => $request->url,
            'status' => $request->status ?? true,
        ]);

        return redirect()->back()->with('success', 'Version created successfully.');
    }

    // Update an existing version
    public function update(Request $request, LandingPage $landing_page, LandingPageVersion $version)
    {
        $request->validate([
            'name' => 'required|string|max:50'
        ]);


        $version->update([
            'name' => $request->name,
            'description' => $request->description,
            'url' => $request->url,
            'status' => $request->status ?? true,
        ]);

        return redirect()->back()->with('success', 'Version updated successfully.');
    }

    // Delete a version
    public function destroy(LandingPage $landing_page, LandingPageVersion $version)
    {
        if ($version->landing_page_id !== $landing_page->id) {
            abort(404);
        }

        $version->delete();

        return redirect()->back()->with('success', 'Version deleted successfully.');
    }
}