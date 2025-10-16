<?php

namespace App\Http\Controllers;

use App\DatatableTrait;
use App\Models\Postback;
use App\Models\PostbackApiRequests;
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
  public function getApiRequests($postbackId)
  {
    try {
      return response()->json([
        'success' => true,
        'data' => PostbackApiRequests::where('postback_id', $postbackId)
          ->orderBy('created_at', 'desc')
          ->get([
            'id',
            'request_id',
            'service',
            'endpoint',
            'method',
            'request_data',
            'response_data',
            'status_code',
            'error_message',
            'response_time_ms',
            'created_at'
          ])
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
    $newStatus = $validated['status'];
    $postback->status = $newStatus;
    // Only update the message if it's provided in the request.
    // Otherwise, keep the existing message (e.g., the original failure reason).
    if (isset($validated['message'])) {
      $postback->message = $validated['message'];
    }
    // Update the processed_at timestamp if the status is processed.
    if ($newStatus == Postback::statusProcessed()) {
      $postback->processed_at = now();
    }
    //Delete payout if status is pending
    if ($newStatus == Postback::statusPending()->value) {
      $postback->payout = null;
    }

    $postback->save();
    add_flash_message(type: "success", message: "Postback status updated successfully.");
    // Redirect back to the index page. Inertia will handle the success flash message.
    return redirect()->route('postbacks.index');
  }

  /**
   * Force sync a single postback to find its payout.
   *
   * @param Postback $postback
   * @return RedirectResponse
   */
  public function forceSync(Postback $postback, PostbackService $postbackService): RedirectResponse
  {
    add_flash_message(type: "info", message: "Syncing postback...");
    if (!in_array($postback->status, [Postback::statusPending(), Postback::statusFailed()])) {
      add_flash_message(type: "error", message: "Only pending or failed postbacks can be synced.");
      return back();
    }

    try {
      $postbackService->forceSyncPostback($postback);
    } catch (\Exception $e) {
      add_flash_message(type: "error", message: "An unexpected error occurred during the sync: " . $e->getMessage());
    }



    return back();
  }
}
