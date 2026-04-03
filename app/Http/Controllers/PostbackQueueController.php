<?php

namespace App\Http\Controllers;

use App\Enums\PostbackVendor;
use App\Http\Requests\UpdatePostbackStatusRequest;
use App\Models\PostbackApiRequests;
use App\Models\PostbackQueue;
use App\Models\PostbackStatus;
use App\Services\PostbackService;
use App\Traits\DatatableTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PostbackQueueController extends Controller
{
  use DatatableTrait;

  public function index(Request $req)
  {
    $query = PostbackQueue::select('*');

    $table = $this->processDatatableQuery(
      query: $query,
      request: $req,
      searchableColumns: ['click_id', 'transaction_id', 'event'],
      filterConfig: [
        'vendor' => ['type' => 'lower'],
        'status' => ['type' => 'lower'],
        'from_date' => ['type' => 'from_date', 'column' => 'created_at'],
        'to_date' => ['type' => 'to_date', 'column' => 'created_at'],
      ],
      allowedSort: ['id', 'offer_id', 'status', 'vendor', 'payout', 'event', 'created_at', 'updated_at'],
      defaultSort: 'created_at:desc',
    );

    $statusFilterOptions = PostbackStatus::toArray();
    $vendorLabelMap = array_column(PostbackVendor::toArray(), 'label', 'value');
    $vendorFilterOptions = PostbackQueue::select('vendor')
      ->distinct()
      ->get()
      ->map(function ($item) use ($vendorLabelMap) {
        return [
          'value' => $item->vendor,
          'label' => $vendorLabelMap[$item->vendor] ?? "Other ({$item->vendor})",
        ];
      });
    $view = Inertia::render('postbacks/queue-legacy', [
      'rows' => $table['rows'],
      'meta' => $table['meta'],
      'state' => $table['state'],
      'data' => [
        'vendorFilterOptions' => $vendorFilterOptions,
        'statusFilterOptions' => $statusFilterOptions,
      ],
    ]);

    return $view;
  }

  public function destroy(Request $request, PostbackQueue $postbackQueue): RedirectResponse
  {
    $postbackQueue->delete();
    session()->flash('success', 'Postback deleted successfully');

    return redirect()->back();
  }

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
            'created_at',
          ]),
      ]);
    } catch (\Throwable $e) {
      return response()->json(
        [
          'success' => false,
          'message' => 'Error getting API requests',
          'error' => $e->getMessage(),
        ],
        500,
      );
    }
  }

  public function updateStatus(UpdatePostbackStatusRequest $request, PostbackQueue $postbackQueue): RedirectResponse
  {
    $validated = $request->validated();
    $newStatus = $validated['status'];
    $postbackQueue->status = $newStatus;

    if (isset($validated['message'])) {
      $postbackQueue->message = $validated['message'];
    }

    if ($newStatus == PostbackQueue::statusProcessed()) {
      $postbackQueue->processed_at = now();
    }

    if ($newStatus == PostbackQueue::statusPending()->value) {
      $postbackQueue->payout = null;
    }

    $postbackQueue->save();
    add_flash_message(type: 'success', message: 'Postback status updated successfully.');

    return redirect()->route('postbacks.queue.index');
  }

  public function forceSync(PostbackQueue $postbackQueue, PostbackService $postbackService): RedirectResponse
  {
    add_flash_message(type: 'info', message: 'Syncing postback...');

    if (!in_array($postbackQueue->status, [PostbackQueue::statusPending(), PostbackQueue::statusFailed()])) {
      add_flash_message(type: 'error', message: 'Only pending or failed postbacks can be synced.');

      return back();
    }

    try {
      $postbackService->forceSyncPostback($postbackQueue);
    } catch (\Exception $e) {
      add_flash_message(type: 'error', message: 'An unexpected error occurred during the sync: ' . $e->getMessage());
    }

    return back();
  }
}
