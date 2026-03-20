<?php

namespace App\Http\Controllers;

use App\Models\Vertical;
use App\Http\Requests\Verticals\StoreRequest;
use App\Http\Requests\Verticals\UpdateRequest;
use Inertia\Inertia;

class VerticalController extends Controller
{
    public function index()
    {
        $verticals = Vertical::latest()->get();

        return Inertia::render('verticals/Index', [
            'verticals' => $verticals,
        ]);
    }

    public function create()
    {
        return Inertia::render('verticals/Create');
    }

    public function store(StoreRequest $request)
    {
        Vertical::create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('verticals.index')
            ->with('success', 'Vertical created successfully.');
    }

    public function edit(Vertical $vertical)
    {
        return Inertia::render('verticals/Edit', [
            'vertical' => $vertical,
        ]);
    }

    public function update(UpdateRequest $request, Vertical $vertical)
    {
        $vertical->update([
            ...$request->validated(),
            'updated_user_id' => auth()->id(),
        ]);

        return redirect()->route('verticals.index')
            ->with('success', 'Vertical updated successfully.');
    }

    public function destroy(Vertical $vertical)
    {
        $vertical->delete();

        return redirect()->route('verticals.index')
            ->with('success', 'Vertical deleted successfully.');
    }
}