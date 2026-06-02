<?php

namespace App\Http\Controllers;

use App\Models\Field;
use App\Models\LandingPage;
use App\Models\LandingPageColumn;
use App\Models\Lead;
use App\Models\TrafficLog;
use App\Traits\DatatableTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LandingPageLeadsController extends Controller
{
  use DatatableTrait;

  /**
   * Traffic columns surfaced via the `latest_tl` join (see applyLatestTrafficLogJoin).
   * Listed here once and consumed by:
   *   - the JOIN's projected SELECT aliases (`latest_<col>`)
   *   - `allowedSort` so the toolbar can sort by any of them
   * Keep the list narrow — every column added here travels in every request.
   */
  private const SORTABLE_TRAFFIC_COLUMNS = ['browser', 'os', 'device_type', 'state', 'city', 'postal_code', 'ip_address', 'referrer'];

  /** Static US states catalog used by the State filter. */
  private const US_STATES = [
    'AL',
    'AK',
    'AZ',
    'AR',
    'CA',
    'CO',
    'CT',
    'DE',
    'FL',
    'GA',
    'HI',
    'ID',
    'IL',
    'IN',
    'IA',
    'KS',
    'KY',
    'LA',
    'ME',
    'MD',
    'MA',
    'MI',
    'MN',
    'MS',
    'MO',
    'MT',
    'NE',
    'NV',
    'NH',
    'NJ',
    'NM',
    'NY',
    'NC',
    'ND',
    'OH',
    'OK',
    'OR',
    'PA',
    'RI',
    'SC',
    'SD',
    'TN',
    'TX',
    'UT',
    'VT',
    'VA',
    'WA',
    'WV',
    'WI',
    'WY',
    'DC',
  ];

  /** Device type values produced by `DeviceDetectionService` (mobile|desktop). */
  private const DEVICE_TYPE_OPTIONS = ['mobile', 'desktop'];

  /** Common OS values; matched with LIKE so variants like "Windows NT 10.0" still match "Windows". */
  private const OS_OPTIONS = ['Windows', 'Mac', 'iOS', 'Android', 'Linux'];

  /**
   * Baseline column set surfaced when a landing has no `landing_page_columns` configured.
   *
   * @var array<int, array{key: string, label: string, source: string, reference: string}>
   */
  private const BASELINE_DESCRIPTORS = [
    ['key' => 'meta:fingerprint', 'label' => 'Fingerprint', 'source' => 'meta', 'reference' => 'fingerprint'],
    ['key' => 'meta:created_at', 'label' => 'Created At', 'source' => 'meta', 'reference' => 'created_at'],
    ['key' => 'traffic:postal_code', 'label' => 'Geo Postal Code', 'source' => 'traffic', 'reference' => 'postal_code'],
    ['key' => 'traffic:ip_address', 'label' => 'Geo IP Address', 'source' => 'traffic', 'reference' => 'ip_address'],
    ['key' => 'traffic:state', 'label' => 'Geo State', 'source' => 'traffic', 'reference' => 'state'],
    ['key' => 'traffic:city', 'label' => 'Geo City', 'source' => 'traffic', 'reference' => 'city'],
    ['key' => 'traffic:browser', 'label' => 'Browser', 'source' => 'traffic', 'reference' => 'browser'],
    ['key' => 'traffic:os', 'label' => 'OS', 'source' => 'traffic', 'reference' => 'os'],
    ['key' => 'traffic:device_type', 'label' => 'Device Type', 'source' => 'traffic', 'reference' => 'device_type'],
    ['key' => 'traffic:referrer', 'label' => 'Referrer', 'source' => 'traffic', 'reference' => 'referrer'],
  ];

  public function index(LandingPage $landingPage, Request $request): Response
  {
    $landingPage->load('columns');

    [$descriptors, $usingDefaults] = $this->buildDescriptors($landingPage);

    $versions = $landingPage
      ->versions()
      ->orderBy('id')
      ->get(['id', 'path', 'name'])
      ->all();

    // Version filter lives in the same `filters` blob the datatable toolbar uses,
    // but is applied to the traffic_logs subquery (not the leads table).
    $filters = json_decode($request->input('filters', '[]'), true) ?? [];
    $versionFilter = collect($filters)->firstWhere('id', 'version');
    $selectedVersions = collect(Arr::wrap($versionFilter['value'] ?? []))
      ->filter(fn($v) => $v !== '' && $v !== null)
      ->map(fn($v) => (int) $v)
      ->values()
      ->all();

    $fingerprintSubquery = TrafficLog::query()
      ->where('landing_id', $landingPage->id)
      ->when(!empty($selectedVersions), fn($q) => $q->whereIn('landing_page_version_id', $selectedVersions))
      ->select('fingerprint');

    $query = Lead::query()
      ->select('leads.*')
      ->whereIn('leads.fingerprint', $fingerprintSubquery)
      ->with(['leadFieldResponses.field', 'latestTrafficLog.landingPageVersion']);

    // Join the latest traffic_log row per lead as `latest_tl` so its columns are
    // first-class joined fields, usable in WHERE / ORDER BY / SELECT in a single
    // query trip per lead. Strategy is driver-aware (LATERAL on Postgres,
    // portable id-subquery elsewhere). See applyLatestTrafficLogJoin().
    $this->applyLatestTrafficLogJoin($query);

    // Default page size for this viewer is 25 (DatatableTrait default is 10).
    if (!$request->has('per_page')) {
      $request->merge(['per_page' => 25]);
    }

    $sortableLatestColumns = collect(self::SORTABLE_TRAFFIC_COLUMNS)->map(fn($c) => 'latest_' . $c)->all();

    $table = $this->processDatatableQuery(
      query: $query,
      request: $request,
      searchableColumns: [],
      filterConfig: [
        'from_date' => ['type' => 'from_date', 'column' => 'leads.created_at'],
        'to_date' => ['type' => 'to_date', 'column' => 'leads.created_at'],
        // Filters now reference real joined columns — DatatableTrait handles them natively.
        'device_type' => ['type' => 'exact', 'column' => 'latest_tl.device_type'],
        'state' => ['type' => 'exact', 'column' => 'latest_tl.state'],
        'os' => ['type' => 'like', 'column' => 'latest_tl.os'],
      ],
      allowedSort: array_merge(['id', 'created_at'], $sortableLatestColumns),
      defaultSort: 'created_at:desc',
    );

    $table['rows']->through(fn(Lead $lead) => $this->transformLead($lead, $descriptors));

    return Inertia::render('landings/leads', [
      'rows' => $table['rows'],
      'meta' => $table['meta'],
      'state' => $table['state'],
      'data' => [
        'landing_page' => $landingPage->only(['id', 'name', 'url']),
        'descriptors' => $descriptors,
        'versions' => $versions,
        'selected_versions' => $selectedVersions,
        'using_defaults' => $usingDefaults,
        'filter_options' => [
          'device_type' => array_map(fn($v) => ['value' => $v, 'label' => ucfirst($v)], self::DEVICE_TYPE_OPTIONS),
          'state' => array_map(fn($v) => ['value' => $v, 'label' => $v], self::US_STATES),
          'os' => array_map(fn($v) => ['value' => $v, 'label' => $v], self::OS_OPTIONS),
        ],
      ],
    ]);
  }

  /**
   * @return array{0: array<int, array{key: string, label: string, source: string, reference: string}>, 1: bool}
   */
  private function buildDescriptors(LandingPage $landingPage): array
  {
    if ($landingPage->columns->isEmpty()) {
      return [self::BASELINE_DESCRIPTORS, true];
    }

    $fieldIds = $landingPage->columns->where('source', LandingPageColumn::SOURCE_FIELD)->pluck('reference')->map(fn($ref) => (int) $ref)->all();

    $fieldsById = Field::query()
      ->whereIn('id', $fieldIds)
      ->get(['id', 'name', 'label'])
      ->keyBy('id');

    $descriptors = $landingPage->columns
      ->map(function (LandingPageColumn $col) use ($fieldsById) {
        if ($col->source === LandingPageColumn::SOURCE_FIELD) {
          $field = $fieldsById->get((int) $col->reference);
          return [
            'key' => 'field:' . $col->reference,
            'label' => $field?->label ?: $field?->name ?? 'Unknown field',
            'source' => 'field',
            'reference' => (string) $col->reference,
          ];
        }

        return [
          'key' => 'traffic:' . $col->reference,
          'label' => LandingPageColumn::trafficLabel($col->reference),
          'source' => 'traffic',
          'reference' => $col->reference,
        ];
      })
      ->all();

    return [array_values($descriptors), false];
  }

  /**
   * @param  array<int, array{key: string, label: string, source: string, reference: string}>  $descriptors
   * @return array<string, mixed>
   */
  private function transformLead(Lead $lead, array $descriptors): array
  {
    $trafficLog = $lead->latestTrafficLog;
    $version = $trafficLog?->landingPageVersion;

    $values = [];
    foreach ($descriptors as $descriptor) {
      $values[$descriptor['key']] = $this->resolveValue($lead, $trafficLog, $descriptor);
    }

    return [
      'id' => $lead->id,
      'fingerprint' => $lead->fingerprint,
      'created_at' => $lead->created_at?->toIso8601String(),
      'version' => $version ? ['id' => $version->id, 'path' => $version->path] : null,
      'values' => $values,
    ];
  }

  /**
   * @param  array{key: string, label: string, source: string, reference: string}  $descriptor
   */
  private function resolveValue(Lead $lead, ?TrafficLog $trafficLog, array $descriptor): mixed
  {
    return match ($descriptor['source']) {
      'meta' => $this->resolveMetaValue($lead, $descriptor['reference']),
      'field' => $lead->leadFieldResponses->firstWhere('field_id', (int) $descriptor['reference'])?->value,
      'traffic' => $trafficLog?->{$descriptor['reference']},
      default => null,
    };
  }

  private function resolveMetaValue(Lead $lead, string $reference): mixed
  {
    if ($reference === 'created_at') {
      return $lead->created_at?->toIso8601String();
    }
    return $lead->{$reference};
  }

  /**
   * Join the most recent traffic_log per lead as `latest_tl`, exposing
   * `latest_tl.<col>` (real joined columns, usable in WHERE/ORDER BY) plus
   * `latest_<col>` SELECT aliases used by the frontend sort keys.
   *
   * Strategy is driver-aware:
   *   - PostgreSQL: `LEFT JOIN LATERAL (...) ON TRUE` — the lateral subquery
   *     can reference outer `leads.fingerprint` and only runs ONCE per lead,
   *     returning all needed columns in a single trip.
   *   - Other drivers (SQLite for tests): one correlated subquery picks the
   *     latest traffic_log id per lead, then a regular JOIN materializes
   *     that row's columns. Same shape, portable, still single-trip per lead.
   */
  private function applyLatestTrafficLogJoin(Builder $query): void
  {
    $driver = DB::connection()->getDriverName();
    $cols = self::SORTABLE_TRAFFIC_COLUMNS;

    if ($driver === 'pgsql') {
      $colList = implode(', ', $cols);
      // ON TRUE is intentional — the actual correlation lives inside the
      // LATERAL subquery via `WHERE traffic_logs.fingerprint = leads.fingerprint`.
      $query->leftJoin(
        DB::raw(
          "LATERAL (SELECT {$colList} FROM traffic_logs WHERE traffic_logs.fingerprint = leads.fingerprint ORDER BY created_at DESC LIMIT 1) AS latest_tl",
        ),
        DB::raw('1'),
        '=',
        DB::raw('1'),
      );
    } else {
      // Portable: pick the latest id once per lead, then JOIN the full row.
      $query->leftJoin('traffic_logs as latest_tl', function ($join) {
        $join->on(
          'latest_tl.id',
          '=',
          DB::raw('(SELECT id FROM traffic_logs t WHERE t.fingerprint = leads.fingerprint ORDER BY t.created_at DESC LIMIT 1)'),
        );
      });
    }

    // SELECT aliases so the frontend column ids (latest_device_type, ...)
    // map cleanly onto allowedSort targets.
    foreach ($cols as $col) {
      $query->addSelect(DB::raw("latest_tl.{$col} AS latest_{$col}"));
    }
  }
}
