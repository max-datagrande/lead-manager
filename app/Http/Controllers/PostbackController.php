<?php

namespace App\Http\Controllers;

use App\Models\Postback;
use App\Services\PostbackService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PostbackController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index(Request $req)
  {
    $q = Postback::select('*');
    // --- BÚSQUEDA GLOBAL (opcional) ---
    $search = trim((string) $req->input('search', ''));
    if ($search !== '') {
      $q->where(function ($w) use ($search) {
        $like = "%{$search}%";
        $w->where('clid', 'like', $like)
          ->orWhere('txid', 'like', $like)
          ->orWhere('event', 'like', $like)
          ->orWhere('failure_reason', 'like', $like);
      });
    }
    // --- FILTROS POR COLUMNA ---
    $filters = json_decode($req->input('filters', '[]'), true) ?? [];
    foreach ($filters as $f) {
      $id = $f['id'] ?? null;
      $val = $f['value'] ?? null;
      if ($val === null || $val === '' || (is_array($val) && empty($val))) continue;
      switch ($id) {
        case 'vendor':
          if (is_array($val)) {
            $q->whereIn('vendor', $val);
          } else {
            $q->where('vendor', strtolower($val));
          }
          break;
        case 'status':
          if (is_array($val)) {
            $q->whereIn('status', $val);
          } else {
            $q->where('status', strtolower($val));
          }
          break;
      }
    }
    $allowedSort = [
      'vendor',
      'status',
      'offer_id',
      'payout',
      'created_at',
      'updated_at',
      'event',
      'failure_reason',
      'id'
    ];

    $sort = $req->input('sort', 'created_at:desc'); // ej: "created_at:desc"

    if ($sort) {
      [$col, $dir] = get_sort_data($sort);
      $isAllowSorting = in_array($col, $allowedSort, true);
      if ($isAllowSorting) {
        $q->orderBy($col, $dir);
      }
    } else {
      $q->orderByDesc('created_at'); //Sort por defecto
    }
    // --- PAGINACIÓN ---
    $perPage = (int) $req->input('per_page', 10);
    $perPage = max(1, min($perPage, 100));
    $page = (int) $req->input('page', 1);
    $queryParams = $req->query();
    $p = $q->paginate($perPage, ['*'], 'page', $page)->appends($queryParams);

    //Vendors
    $vendorNames = [
      'ni' => 'Natural Intelligence',
      'maxconv' => 'MaxConv'
    ];
    $vendors = Postback::select('vendor')->distinct()->get()->map(function ($item) use ($vendorNames) {
      return [
        'value' => $item->vendor,
        'label' => $vendorNames[$item->vendor] ?? $item->vendor
      ];
    });
    //status
    $states = [
      [
        'label' => "Pending",
        'value' => Postback::STATUS_PENDING,
        'iconName' => 'Badge'
      ],
      [
        'label' => "Processed",
        'value' => Postback::STATUS_PROCESSED,
        'iconName' => 'BadgeCheck'
      ],
      [
        'label' => "Failed",
        'value' => Postback::STATUS_FAILED,
        'iconName' => 'BadgeAlert'
      ]
    ];
    return Inertia::render('postback/index', [
      'rows' => $p,
      'meta' => [
        'total' => $p->total(),
        'per_page' => $p->perPage(),
        'current_page' => $p->currentPage(),
        'last_page' => $p->lastPage(),
        'from' => $p->firstItem(),
        'to' => $p->lastItem(),
      ],
      'state' => [
        'search' => $search,
        'filters' => $filters,
        'sort' => $sort,
        'page' => $page,
        'per_page' => $perPage,
      ],
      'data' => [
        'vendors' => $vendors,
        'states' => $states
      ]
    ]);
  }
  public function create(Request $request)
  {
    /*  return Inertia::render('postback/create'); */
  }

  public function store(Request $request) {}
  public function update(Request $request) {}
  public function destroy(Request $request) {}

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
}
