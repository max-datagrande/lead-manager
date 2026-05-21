<?php

namespace App\Http\Controllers\Logs;

use App\Enums\PingResultStatus;
use App\Enums\PostResultStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Integration;
use App\Models\Workflow;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BuyerEventLogController extends Controller
{
  private const ALLOWED_SORT = [
    'created_at',
    'stage',
    'event_type',
    'ping_bid',
    'post_bid',
    'final_payout',
    'duration_ms',
    'http_status_code',
    'integration',
    'company',
    'workflow',
  ];

  private const SORT_COLUMN_MAP = [
    'created_at' => 'be.created_at',
    'stage' => 'be.stage',
    'event_type' => 'be.event_type',
    'ping_bid' => 'be.ping_bid',
    'post_bid' => 'be.post_bid',
    'final_payout' => 'be.final_payout',
    'duration_ms' => 'be.duration_ms',
    'http_status_code' => 'be.http_status_code',
    'integration' => 'integrations.name',
    'company' => 'companies.name',
    'workflow' => 'workflows.name',
  ];

  private const FILTER_COLUMN_MAP = [
    'integration_id' => 'be.integration_id',
    'stage' => 'be.stage',
    'event_type' => 'be.event_type',
    'reason' => 'be.reason',
    'workflow_id' => 'lead_dispatches.workflow_id',
    'company_id' => 'integrations.company_id',
  ];

  public function index(Request $request): Response
  {
    $result = $this->runDatatable($request);

    return Inertia::render('ping-post/buyer-events/index', [
      'rows' => $result['rows'],
      'meta' => $result['meta'],
      'state' => $result['state'],
      'data' => [
        'stageOptions' => [
          ['value' => 'pre_dispatch', 'label' => 'Pre-Dispatch'],
          ['value' => 'ping', 'label' => 'Ping'],
          ['value' => 'post', 'label' => 'Post'],
        ],
        'eventTypeOptions' => $this->eventTypeOptions(),
        'reasonOptions' => $this->reasonOptions(),
        'workflows' => Workflow::orderBy('name')->get(['id', 'name']),
        'integrations' => Integration::query()
          ->whereIn('type', ['ping-post', 'post-only'])
          ->orderBy('name')
          ->get(['id', 'name']),
        'companies' => Company::query()
          ->whereHas('integrations', fn($q) => $q->whereIn('type', ['ping-post', 'post-only']))
          ->orderBy('name')
          ->get(['id', 'name']),
      ],
    ]);
  }

  public function export(Request $request): StreamedResponse
  {
    $query = $this->buildBuyerEventsQuery($request)->orderBy('be.created_at', 'desc');

    $delimiter = $request->input('os') === 'windows' ? ';' : ',';
    $filename = 'buyer-events-' . now()->format('Y-m-d-His') . '.csv';

    return new StreamedResponse(
      function () use ($query, $delimiter) {
        $handle = fopen('php://output', 'w');
        fputcsv(
          $handle,
          [
            'Date',
            'Stage',
            'Event Type',
            'Reason',
            'Buyer',
            'Company',
            'Workflow',
            'Dispatch UUID',
            'Fingerprint',
            'Ping Bid',
            'Post Bid',
            'Final Payout',
            'HTTP Status',
            'Duration (ms)',
          ],
          $delimiter,
        );

        $query->cursor()->each(function ($row) use ($handle, $delimiter) {
          fputcsv(
            $handle,
            [
              $row->created_at,
              $row->stage,
              $row->event_type,
              $row->reason ?? '',
              $row->integration_name ?? '',
              $row->company_name ?? '',
              $row->workflow_name ?? '',
              $row->dispatch_uuid ?? '',
              $row->fingerprint ?? '',
              $row->ping_bid !== null ? number_format((float) $row->ping_bid, 4, '.', '') : '',
              $row->post_bid !== null ? number_format((float) $row->post_bid, 4, '.', '') : '',
              $row->final_payout !== null ? number_format((float) $row->final_payout, 4, '.', '') : '',
              $row->http_status_code ?? '',
              $row->duration_ms ?? '',
            ],
            $delimiter,
          );
        });

        fclose($handle);
      },
      200,
      [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => "attachment; filename=\"{$filename}\"",
      ],
    );
  }

  // ── Datatable orchestration ─────────────────────────────────────────

  /**
   * @return array{rows: \Illuminate\Contracts\Pagination\LengthAwarePaginator, meta: array, state: array}
   */
  private function runDatatable(Request $request): array
  {
    $query = $this->buildBuyerEventsQuery($request);

    $sort = (string) $request->input('sort', 'created_at:desc');
    $this->applySort($query, $sort);

    $perPage = max(1, min((int) $request->input('per_page', 50), 100));
    $page = max(1, (int) $request->input('page', 1));

    $paginated = $query->paginate($perPage, ['*'], 'page', $page)->appends($request->query());

    return [
      'rows' => $paginated,
      'meta' => [
        'total' => $paginated->total(),
        'per_page' => $paginated->perPage(),
        'current_page' => $paginated->currentPage(),
        'last_page' => $paginated->lastPage(),
        'from' => $paginated->firstItem(),
        'to' => $paginated->lastItem(),
      ],
      'state' => [
        'search' => (string) $request->input('search', ''),
        'filters' => json_decode($request->input('filters', '[]'), true) ?? [],
        'sort' => $sort,
        'page' => $page,
        'per_page' => $perPage,
      ],
    ];
  }

  /**
   * UNION ALL of post_results + ping_results + dispatch_buyer_events projected to a
   * common shape, wrapped in an outer query so filters / joins / sort / pagination
   * can be applied uniformly. NULLs are CAST explicitly so PostgreSQL accepts the
   * type alignment across branches (SQLite tolerates it either way).
   */
  private function buildBuyerEventsQuery(Request $request): QueryBuilder
  {
    $postSub = DB::table('post_results')->selectRaw(
      "'post_result' AS source,
       'post' AS stage,
       id,
       lead_dispatch_id,
       integration_id,
       status AS event_type,
       rejection_reason AS reason,
       CAST(NULL AS DECIMAL) AS ping_bid,
       price_offered AS post_bid,
       price_final AS final_payout,
       http_status_code,
       duration_ms,
       created_at",
    );

    $pingSub = DB::table('ping_results')->selectRaw(
      "'ping_result' AS source,
       'ping' AS stage,
       id,
       lead_dispatch_id,
       integration_id,
       status AS event_type,
       skip_reason AS reason,
       bid_price AS ping_bid,
       CAST(NULL AS DECIMAL) AS post_bid,
       CAST(NULL AS DECIMAL) AS final_payout,
       http_status_code,
       duration_ms,
       created_at",
    );

    $eventSub = DB::table('dispatch_buyer_events')->selectRaw(
      "'buyer_event' AS source,
       'pre_dispatch' AS stage,
       id,
       lead_dispatch_id,
       integration_id,
       event AS event_type,
       reason,
       CAST(NULL AS DECIMAL) AS ping_bid,
       CAST(NULL AS DECIMAL) AS post_bid,
       CAST(NULL AS DECIMAL) AS final_payout,
       CAST(NULL AS INTEGER) AS http_status_code,
       CAST(NULL AS INTEGER) AS duration_ms,
       created_at",
    );

    $union = $postSub->unionAll($pingSub)->unionAll($eventSub);

    $outer = DB::query()
      ->fromSub($union, 'be')
      ->leftJoin('lead_dispatches', 'lead_dispatches.id', '=', 'be.lead_dispatch_id')
      ->leftJoin('integrations', 'integrations.id', '=', 'be.integration_id')
      ->leftJoin('companies', 'companies.id', '=', 'integrations.company_id')
      ->leftJoin('workflows', 'workflows.id', '=', 'lead_dispatches.workflow_id')
      ->select(
        'be.source',
        'be.stage',
        'be.id',
        'be.lead_dispatch_id',
        'be.integration_id',
        'be.event_type',
        'be.reason',
        'be.ping_bid',
        'be.post_bid',
        'be.final_payout',
        'be.http_status_code',
        'be.duration_ms',
        'be.created_at',
        'lead_dispatches.dispatch_uuid as dispatch_uuid',
        'lead_dispatches.fingerprint as fingerprint',
        'lead_dispatches.workflow_id as workflow_id',
        'integrations.name as integration_name',
        'integrations.company_id as company_id',
        'companies.name as company_name',
        'workflows.name as workflow_name',
      );

    $this->applyFilters($outer, $request);
    $this->applyGlobalSearch($outer, (string) $request->input('search', ''));

    return $outer;
  }

  private function applyFilters(QueryBuilder $query, Request $request): void
  {
    $filters = json_decode($request->input('filters', '[]'), true) ?? [];

    foreach ($filters as $f) {
      $id = $f['id'] ?? null;
      $val = $f['value'] ?? null;

      if ($val === null || $val === '' || (is_array($val) && empty($val))) {
        continue;
      }

      if ($id === 'from_date') {
        $query->whereDate('be.created_at', '>=', $val);
        continue;
      }
      if ($id === 'to_date') {
        $query->whereDate('be.created_at', '<=', $val);
        continue;
      }

      if (!isset(self::FILTER_COLUMN_MAP[$id])) {
        continue;
      }

      $column = self::FILTER_COLUMN_MAP[$id];
      if (is_array($val)) {
        $query->whereIn($column, $val);
      } else {
        $query->where($column, $val);
      }
    }
  }

  private function applyGlobalSearch(QueryBuilder $query, string $search): void
  {
    if (trim($search) === '') {
      return;
    }
    $like = '%' . $search . '%';
    $query->where(function ($w) use ($like) {
      $w->where('lead_dispatches.dispatch_uuid', 'like', $like)->orWhere('lead_dispatches.fingerprint', 'like', $like);
    });
  }

  private function applySort(QueryBuilder $query, string $sort): void
  {
    [$col, $dir] = get_sort_data($sort);

    if (!in_array($col, self::ALLOWED_SORT, true)) {
      $query->orderBy('be.created_at', 'desc');
      return;
    }

    $query->orderBy(self::SORT_COLUMN_MAP[$col], $dir);
  }

  /**
   * @return array<int, array{value: string, label: string}>
   */
  private function eventTypeOptions(): array
  {
    $set = [];
    foreach (PingResultStatus::toArray() as $opt) {
      $set[$opt['value']] = $opt;
    }
    foreach (PostResultStatus::toArray() as $opt) {
      $set[$opt['value']] = $opt;
    }
    $set['filtered'] = ['value' => 'filtered', 'label' => 'Filtered'];
    $set['skipped'] = ['value' => 'skipped', 'label' => 'Skipped'];
    ksort($set);
    return array_values($set);
  }

  /**
   * @return array<int, array{value: string, label: string}>
   */
  private function reasonOptions(): array
  {
    $reasons = ['ineligible', 'cap_exceeded', 'duplicate', 'inactive', 'no_config', 'price_below_threshold', 'schedule_out_of_window'];
    sort($reasons);
    return array_map(fn(string $r) => ['value' => $r, 'label' => ucfirst(str_replace('_', ' ', $r))], $reasons);
  }
}
