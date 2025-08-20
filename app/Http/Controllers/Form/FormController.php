<?php

namespace App\Http\Controllers\Form;

use App\Models\Form;
use App\Models\Field;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Arr;
use App\Http\Controllers\Controller;

class FormController extends Controller
{

  public function index()
  {
    $data = [
      'forms'  => Form::with('fields:id,name')->get(),
      'fields' => Field::all(),
    ];
    return Inertia::render('Forms/Index', $data);
  }

  public function create()
  {
    //
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request)
  {
    try {
      $validated = $request->validate([
        'name'         => 'required|string|max:255',
        'description'  => 'required|string|max:255',
        'fields'       => 'array',                 // debe llegar un array
      ]);
      $form = Form::create(Arr::only($validated, ['name', 'description']));
      $form->fields()->sync($validated['fields'] ?? []);  // attach / detach automático
      addFlashMessage('success', 'Form created successfully.');
      return back();
    } catch (\Throwable $th) {
      $message = $th->getMessage();
      addFlashMessage('error', 'Something went wrong: ' . $message);
      return back()->withErrors(['message' => 'Something went wrong.']);
    }
  }

  /**
   * Display the specified resource.
   */
  public function show(Form $form)
  {
    //
  }

  /**
   * Show the form for editing the specified resource.
   */
  public function edit(Form $form)
  {
    //
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, Form $form)
  {
    try {
      $validated = $request->validate([
        'name'         => 'required|string|max:255',
        'description'  => 'required|string|max:255',
        'fields'       => 'array',                 // debe llegar un array
      ]);
      $form->update(Arr::only($validated, ['name', 'description']));
      $form->fields()->sync($validated['fields'] ?? []);  // attach / detach automático
      addFlashMessage('success', 'Form updated successfully.');
      return back();
    } catch (\Throwable $th) {
      $message = $th->getMessage();
      addFlashMessage('error', 'Something went wrong: ' . $message);
      return back()->withErrors(['message' => 'Something went wrong.']);
    }
  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy(Form $form)
  {
    //
  }
}
