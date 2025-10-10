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
    $sort = $request->get('sort', 'created_at:desc');
    [$col, $dir] = get_sort_data($sort);
    $entries = Field::query()
      ->orderBy($col, $dir)
      ->get();
    return Inertia::render('fields/index', [
      'rows' => $entries,
      'state' => compact('sort')
    ]);
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request)
  {
    $validated = $request->validate([
      'name' => 'required|string|max:255',
      'label' => 'required|string|max:255',
      'possible_values' => 'nullable|array',
    ]);

    Field::create($validated);
    add_flash_message('success', 'Field created successfully.');
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
        'possible_values' => 'nullable|array',
      ]);
      $field->update($validated);
      add_flash_message('success', 'Field updated successfully.');
      return  back();
    } catch (\Throwable $th) {
      $message = $th->getMessage();
      add_flash_message('error', 'Something went wrong: ' . $message);
      return back()->withErrors(['message' => 'Something went wrong.']);
    }
  }
  /**
   * Remove the specified resource from storage.
   */
  public function destroy(Field $field)
  {
    $field->delete();
    add_flash_message('success', 'Field deleted successfully.');
    return  back();
  }
}
