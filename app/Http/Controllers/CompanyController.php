<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index(Request $request)
  {
    $sort = $request->get('sort', 'created_at:desc');
    [$col, $dir] = get_sort_data($sort);
    $entries = Company::query()
      ->orderBy($col, $dir)
      ->get();
    return Inertia::render('companies/index', [
      'rows' => $entries,
      'filters' => compact('sort')
    ]);
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request)
  {
    $validated = $request->validate([
      'name' => 'required|string|max:100',
      'name' => 'required|string|max:100',
      'contact_email' => 'nullable|email|max:100',
      'contact_phone' => 'nullable|string|max:50',
      'contact_name' => 'nullable|string|max:255|unique:companies,contact_name',
      'active' => 'boolean',
    ]);

    Company::create($validated);
    add_flash_message('success', 'Company created successfully.');
    return  back();
  }
  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, Company $company)
  {
    try {
      $validated = $request->validate([
        'name' => 'required|string|max:100',
        'contact_email' => 'nullable|email|max:100',
        'contact_phone' => 'nullable|string|max:50',
        'company_name' => 'nullable|string|max:255|unique:companies,company_name,' . $company->id,
        'active' => 'boolean',
      ]);
      $company->update($validated);
      add_flash_message('success', 'Company updated successfully.');
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
  public function destroy(Company $company)
  {
    $company->delete();
    add_flash_message('success', 'Company deleted successfully.');
    return  back();
  }
}
