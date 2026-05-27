<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMappingFindingRequest;
use App\Models\IntegrationMappingFinding;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MappingFindingController extends Controller
{
  /**
   * List mapping findings, defaulting to open ones.
   */
  public function index(Request $request)
  {
    $status = $request->get('status', IntegrationMappingFinding::STATUS_OPEN);

    $findings = IntegrationMappingFinding::query()
      ->when($status !== 'all', fn($q) => $q->where('status', $status))
      ->with(['integration:id,name', 'field:id,name,label,possible_values'])
      ->orderByDesc('last_seen_at')
      ->get();

    return Inertia::render('admin/mapping-findings/index', [
      'rows' => $findings,
      'filters' => ['status' => $status],
    ]);
  }

  /**
   * Update a finding's status (ignore an intentional gap, or reopen it).
   */
  public function update(UpdateMappingFindingRequest $request, IntegrationMappingFinding $mappingFinding)
  {
    $mappingFinding->update(['status' => $request->validated()['status']]);

    add_flash_message(type: 'success', message: 'Finding updated.');

    return back();
  }
}
