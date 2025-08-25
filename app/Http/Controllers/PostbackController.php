<?php

namespace App\Http\Controllers;

use App\DatatableTrait;
use App\Models\Postback;
use App\Services\PostbackService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PostbackController extends Controller
{
  use DatatableTrait;

  /**
   * Display a listing of the resource.
   */
  public function index(Request $req)
  {
    $query = Postback::select('*');

    // Configuración de búsqueda global
    $searchableColumns = [
      'clid',
      'txid',
      'event',
      'failure_reason'
    ];

    // Configuración de filtros
    $filterConfig = [
      'vendor' => ['type' => 'lower'],
      'status' => ['type' => 'lower'],
    ];

    // Configuración de ordenamiento
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

    // Procesar consulta usando el trait
    $result = $this->processDatatableQuery(
      $query,
      $req,
      $searchableColumns,
      $filterConfig,
      $allowedSort,
      'created_at:desc',
      10,
      100
    );

    // Datos adicionales para filtros
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
      'rows' => $result['rows'],
      'meta' => $result['meta'],
      'state' => $result['state'],
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
