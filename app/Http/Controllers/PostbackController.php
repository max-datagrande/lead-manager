<?php

namespace App\Http\Controllers;

use App\DatatableTrait;
use App\Models\Postback;
use App\Enums\PostbackVendor;
use App\Services\PostbackService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Requests\UpdatePostbackStatusRequest;
use Illuminate\Http\RedirectResponse;

class PostbackController extends Controller
{
  use DatatableTrait;

  /**
   * Display a listing of the resource.
   */
  public function index(Request $req)
  {
    $query = Postback::select('*');

    $table = $this->processDatatableQuery(
      query: $query,
      request: $req,
      searchableColumns: [ // Configuración de búsqueda global
        'click_id',
        'transaction_id',
        'event',
      ],
      filterConfig: [ // Configuración de filtros
        'vendor' => ['type' => 'lower'],
        'status' => ['type' => 'lower'],
        'from_date' => ['type' => 'from_date', 'column' => 'created_at'],
        'to_date' => ['type' => 'to_date', 'column' => 'created_at'],
      ],
      allowedSort: [ // Configuración de ordenamiento
        'id',
        'offer_id',
        'status',
        'vendor',
        'payout',
        'event',
        'created_at',
        'updated_at'
      ],
      defaultSort: 'created_at:desc',
      defaultPerPage: 10,
      maxPerPage: 100
    );

    // Datos adicionales para filtros
    $statusFilterOptions = \App\Models\PostbackStatus::toArray();
    $vendorLabelMap = array_column(PostbackVendor::toArray(), 'label', 'value');
    $vendorFilterOptions = Postback::select('vendor')->distinct()->get()->map(function ($item) use ($vendorLabelMap) {
      return [
        'value' => $item->vendor,
        'label' => $vendorLabelMap[$item->vendor] ?? "Other ({$item->vendor})"
      ];
    });

    return Inertia::render('postback/index', [
      'rows' => $table['rows'],
      'meta' => $table['meta'],
      'state' => $table['state'],
      'data' => [
        'vendorFilterOptions' => $vendorFilterOptions,
        'statusFilterOptions' => $statusFilterOptions
      ]
    ]);
  }
  public function create(Request $request) {}
  public function store(Request $request) {}
  public function update(Request $request) {}
  public function destroy(Request $request, Postback $postback)
  {
    if (!$postback) {
      session()->flash('error', 'Postback not found');
      return redirect()->back();
    }
    $postback->delete();
    session()->flash('success', 'Postback deleted successfully');
    return redirect()->back();
  }

  /**
   * Display the specified resource.
   */
  public function show(Postback $postback)
  {
    //
  }

  /**
   * Get API requests for a specific postback
   */
  public function getApiRequests(Request $request, $postbackId)
  {
    $postbackServices = app(PostbackService::class);
    try {
      $requests = $postbackServices->getApiRequests($postbackId);
      return response()->json([
        'success' => true,
        'data' => $requests
      ]);
    } catch (\Throwable $e) {
      session()->flash('error', $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => 'Error getting API requests',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  /**
   * Update the status of a specific postback.
   */
  public function updateStatus(UpdatePostbackStatusRequest $request, Postback $postback): RedirectResponse
  {
    $validated = $request->validated();

    $postback->status = $validated['status'];
    // Only update the message if it's provided in the request.
    // Otherwise, keep the existing message (e.g., the original failure reason).
    if (isset($validated['message'])) {
      $postback->message = $validated['message'];
    }

    $postback->save();
    add_flash_message('success', 'Postback status updated successfully.');
    // Redirect back to the index page. Inertia will handle the success flash message.
    return redirect()->route('postbacks.index');
  }
}
