<?php

namespace App\Http\Controllers;

use App\Http\Requests\Verticals\StoreRequest;
use App\Http\Requests\Verticals\UpdateRequest;
use App\Models\Vertical;
use Inertia\Inertia;

class VerticalController extends Controller
{
  public function index()
  {
    $verticals = Vertical::latest()->get();

    return Inertia::render('verticals/index', [
      'verticals' => $verticals,
    ]);
  }

  public function store(StoreRequest $request)
  {
    Vertical::create($request->validated());

    return redirect()->route('verticals.index')->with('success', 'Vertical created successfully.');
  }

  public function update(UpdateRequest $request, Vertical $vertical)
  {
    $vertical->update($request->validated());

    return redirect()->route('verticals.index')->with('success', 'Vertical updated successfully.');
  }

  public function destroy(Vertical $vertical)
  {
    $vertical->delete();

    return redirect()->route('verticals.index')->with('success', 'Vertical deleted successfully.');
  }
}
