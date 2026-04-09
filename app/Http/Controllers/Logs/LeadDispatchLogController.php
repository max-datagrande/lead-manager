<?php

namespace App\Http\Controllers\Logs;

use App\Enums\DispatchStatus;
use App\Enums\PostbackType;
use App\Enums\WorkflowStrategy;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Field;
use App\Models\Integration;
use App\Models\LeadDispatch;
use App\Models\PingResult;
use App\Models\PostbackExecution;
use App\Models\PostResult;
use App\Models\Workflow;
use App\Jobs\PingPost\RetryDispatchJob;
use App\Traits\DatatableTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maxidev\Logger\TailLogger;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadDispatchLogController extends Controller
{
  use DatatableTrait;

  public function index(Request $request): Response
  {
    $originalSort = $request->input('sort', '');
    $query = $this->buildDispatchQuery($request);

    $table = $this->processDatatableQuery(
      query: $query,
      request: $request,
      searchableColumns: ['fingerprint', 'dispatch_uuid', 'utm_source'],
      filterConfig: self::FILTER_CONFIG,
      allowedSort: self::ALLOWED_SORT,
      defaultSort: 'created_at:desc',
    );

    // Restore original sort in state for frontend persistence (join-based sorts clear it)
    if ($originalSort && $table['state']['sort'] !== $originalSort) {
      $table['state']['sort'] = $originalSort;
    }

    $items = collect($table['rows']->items());

    $soldUuids = $items->where('status', DispatchStatus::SOLD)->pluck('dispatch_uuid')->filter()->values()->all();

    $dispatchesWithExecutions = $soldUuids
      ? PostbackExecution::query()->whereIn('source_reference', $soldUuids)->distinct()->pluck('source_reference')->all()
      : [];

    $workflowIds = $items->pluck('workflow_id')->unique()->values()->all();

    $workflowPostbacks = DB::table('postback_workflow')
      ->join('postbacks', 'postbacks.id', '=', 'postback_workflow.postback_id')
      ->where('postbacks.type', PostbackType::INTERNAL->value)
      ->where('postbacks.is_active', true)
      ->whereIn('postback_workflow.workflow_id', $workflowIds)
      ->select('postback_workflow.workflow_id', 'postbacks.id', 'postbacks.uuid', 'postbacks.name', 'postbacks.base_url')
      ->get()
      ->groupBy('workflow_id')
      ->map(fn($items) => $items->values())
      ->all();

    return Inertia::render('ping-post/dispatches/index', [
      'rows' => $table['rows'],
      'meta' => $table['meta'],
      'state' => $table['state'],
      'data' => [
        'statusOptions' => DispatchStatus::toArray(),
        'strategyOptions' => WorkflowStrategy::toArray(),
        'workflows' => Workflow::orderBy('name')->get(['id', 'name']),
        'integrations' => Integration::query()
          ->whereIn('type', ['ping-post', 'post-only'])
          ->orderBy('name')
          ->get(['id', 'name']),
        'companies' => Company::query()
          ->whereHas('integrations', fn($q) => $q->whereIn('type', ['ping-post', 'post-only']))
          ->orderBy('name')
          ->get(['id', 'name']),
        'utmSources' => LeadDispatch::query()
          ->whereNotNull('utm_source')
          ->distinct()
          ->orderBy('utm_source')
          ->pluck('utm_source')
          ->map(fn(string $v) => ['value' => $v, 'label' => $v])
          ->values()
          ->all(),
      ],
      'dispatches_with_executions' => $dispatchesWithExecutions,
      'workflow_postbacks' => $workflowPostbacks,
    ]);
  }

  public function report(Request $request): StreamedResponse
  {
    $query = $this->buildDispatchQuery($request);
    $this->applyGlobalSearch($query, $request->input('search', ''), ['fingerprint', 'dispatch_uuid', 'utm_source']);
    $this->applyColumnFilters($query, json_decode($request->input('filters', '[]'), true) ?? [], self::FILTER_CONFIG);

    $delimiter = $request->input('os') === 'windows' ? ';' : ',';
    $filename = 'dispatch-logs-' . now()->format('Y-m-d-His') . '.csv';

    return new StreamedResponse(
      function () use ($query, $delimiter) {
        $handle = fopen('php://output', 'w');
        fputcsv(
          $handle,
          [
            'ID',
            'UUID',
            'Fingerprint',
            'Workflow',
            'Strategy',
            'Status',
            'Winner',
            'Company',
            'Price',
            'Duration (ms)',
            'UTM Source',
            'Started At',
            'Completed At',
            'Created At',
          ],
          $delimiter,
        );

        $query
          ->with(['workflow:id,name', 'winnerIntegration:id,name,company_id', 'winnerIntegration.company:id,name'])
          ->cursor()
          ->each(function (LeadDispatch $d) use ($handle, $delimiter) {
            fputcsv(
              $handle,
              [
                $d->id,
                $d->dispatch_uuid,
                $d->fingerprint,
                $d->workflow?->name ?? '',
                $d->strategy_used,
                $d->status?->value ?? '',
                $d->winnerIntegration?->name ?? '',
                $d->winnerIntegration?->company?->name ?? '',
                $d->final_price,
                $d->total_duration_ms,
                $d->utm_source ?? '',
                $d->started_at?->toDateTimeString() ?? '',
                $d->completed_at?->toDateTimeString() ?? '',
                $d->created_at?->toDateTimeString() ?? '',
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

  public function show(LeadDispatch $dispatch): Response
  {
    $dispatch->load([
      'workflow',
      'lead',
      'winnerIntegration.company',
      'pingResults.integration.company',
      'postResults.integration.company',
      'postResults.pingResult',
      'buyerEvents.integration',
    ]);

    $rootId = $dispatch->parent_dispatch_id ?? $dispatch->id;
    $allAttempts = LeadDispatch::query()
      ->where(function ($q) use ($rootId) {
        $q->where('id', $rootId)->orWhere('parent_dispatch_id', $rootId);
      })
      ->orderBy('attempt')
      ->get(['id', 'attempt', 'status', 'started_at', 'completed_at']);

    return Inertia::render('ping-post/dispatches/show', [
      'dispatch' => $dispatch,
      'fields' => Field::all(['id', 'name', 'label']),
      'allAttempts' => $allAttempts,
    ]);
  }

  public function timeline(LeadDispatch $dispatch): Response
  {
    $dispatch->load(['workflow', 'winnerIntegration']);

    $timelineLogs = $dispatch->timelineLogs()->orderBy('logged_at')->get();

    $rootId = $dispatch->parent_dispatch_id ?? $dispatch->id;
    $allAttempts = LeadDispatch::query()
      ->where(function ($q) use ($rootId) {
        $q->where('id', $rootId)->orWhere('parent_dispatch_id', $rootId);
      })
      ->orderBy('attempt')
      ->get(['id', 'attempt', 'status', 'started_at', 'completed_at']);

    return Inertia::render('ping-post/dispatches/timeline', [
      'dispatch' => $dispatch,
      'timelineLogs' => $timelineLogs,
      'allAttempts' => $allAttempts,
    ]);
  }

  public function retry(LeadDispatch $dispatch): RedirectResponse
  {
    if (!$dispatch->status->isTerminal()) {
      return back()->with('error', 'Only terminal dispatches can be retried.');
    }

    RetryDispatchJob::dispatch($dispatch->id);

    return back()->with('success', 'Retry dispatch queued. A new attempt will appear shortly.');
  }

  public function resultDetail(string $type, int $id): JsonResponse
  {
    $model = match ($type) {
      'ping' => PingResult::with('integration')->findOrFail($id),
      'post' => PostResult::with('integration')->findOrFail($id),
      default => abort(404),
    };

    return response()->json($model);
  }

  // ── Shared query builder ────────────────────────────────────────────

  private const FILTER_CONFIG = [
    'status' => ['type' => 'exact'],
    'strategy_used' => ['type' => 'exact'],
    'workflow_id' => ['type' => 'exact'],
    'winner_integration_id' => ['type' => 'exact'],
    'utm_source' => ['type' => 'exact'],
    'from_date' => ['type' => 'from_date', 'column' => 'created_at'],
    'to_date' => ['type' => 'to_date', 'column' => 'created_at'],
  ];

  private const ALLOWED_SORT = ['id', 'status', 'strategy_used', 'final_price', 'total_duration_ms', 'created_at', 'utm_source'];

  /**
   * Build the base dispatch query with filters and join-based sorts.
   * Shared between index() and report().
   */
  private function buildDispatchQuery(Request $request): \Illuminate\Database\Eloquent\Builder
  {
    $query = LeadDispatch::query()->with(['workflow:id,name', 'lead', 'winnerIntegration:id,name,company_id', 'winnerIntegration.company:id,name']);

    $filters = json_decode($request->input('filters', '[]'), true) ?? [];
    $companyFilter = collect($filters)->firstWhere('id', 'company_id');
    if ($companyFilter) {
      $companyIds = (array) $companyFilter['value'];
      $query->whereHas('winnerIntegration', fn($q) => $q->whereIn('company_id', $companyIds));
    }

    // Sort by related columns (join-based)
    $sort = $request->input('sort', '');
    [$sortCol] = $sort ? get_sort_data($sort) : ['', 'desc'];
    $joinSorts = [
      'workflow' => ['workflows', 'workflow_id', 'name'],
      'winner_integration' => ['integrations', 'winner_integration_id', 'name'],
      'company' => ['integrations', 'winner_integration_id', 'company_id'],
    ];

    if (isset($joinSorts[$sortCol])) {
      [, $dir] = get_sort_data($sort);
      if ($sortCol === 'company') {
        $query
          ->leftJoin('integrations', 'lead_dispatches.winner_integration_id', '=', 'integrations.id')
          ->leftJoin('companies', 'integrations.company_id', '=', 'companies.id')
          ->orderBy('companies.name', $dir)
          ->select('lead_dispatches.*');
      } else {
        [$joinTable, $fk, $orderCol] = $joinSorts[$sortCol];
        $query
          ->leftJoin($joinTable, "lead_dispatches.{$fk}", '=', "{$joinTable}.id")
          ->orderBy("{$joinTable}.{$orderCol}", $dir)
          ->select('lead_dispatches.*');
      }
      // Clear sort so DatatableTrait doesn't override
      $request->merge(['sort' => '']);
    }

    return $query;
  }
}
