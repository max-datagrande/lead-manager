<?php

namespace App\Http\Controllers\Form;

use App\Models\Field;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class FieldController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index(Request $request)
  {
    $search = $request->input('search');
    $sort = $request->input('sort');
    $direction = $request->input('direction');
    $perPage = 10;
    $query = Field::query();

    if ($search) {
      $query->where('name', 'like', "%{$search}%")
        ->orWhere('label', 'like', "%{$search}%")
        ->orWhere('validation_rules', 'like', "%{$search}%");
    }
    if ($sort && $direction) {
      $query->orderBy($sort, $direction);
    }
    $fields = $query->paginate($perPage)->withQueryString();
    $data = [
      'fields' => $fields,
      'filters' => []
    ];
    if ($search) {
      $data['filters']['search'] = $search;
    }
    if ($sort && $direction) {
      $data['filters']['sort'] = $sort;
      $data['filters']['direction'] = $direction;
    }
    return Inertia::render('Fields', $data);
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request)
  {
    $validated = $request->validate([
      'name' => 'required|string|max:255',
      'label' => 'required|string|max:255',
    ]);

    Field::create($validated);
    addFlashMessage('success', 'Field created successfully.');
    return  back();
  }
  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, Field $field)
  {
    try {
      $validated = $request->validate([
        'name' => 'required|string|max:255',
        'label' => 'required|string|max:255',
      ]);
      $field->update($validated);
      addFlashMessage('success', 'Field updated successfully.');
      return  back();
    } catch (\Throwable $th) {
      $message = $th->getMessage();
      addFlashMessage('error', 'Something went wrong: ' . $message);
      return back()->withErrors(['message' => 'Something went wrong.']);
    }
  }
  /**
   * Remove the specified resource from storage.
   */
  public function destroy(Field $field)
  {
    $field->delete();
    addFlashMessage('success', 'Field deleted successfully.');
    return  back();
  }
}
