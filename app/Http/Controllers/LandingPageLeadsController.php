<?php

namespace App\Http\Controllers;

use App\Models\Field;
use App\Models\LandingPage;
use App\Models\LandingPageColumn;
use App\Models\Lead;
use App\Models\TrafficLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingPageLeadsController extends Controller
{
  /**
   * Baseline column set surfaced when a landing has no `landing_page_columns` configured.
   *
   * @var array<int, array{key: string, label: string, source: string, reference: string}>
   */
  private const BASELINE_DESCRIPTORS = [
    ['key' => 'meta:id', 'label' => 'ID', 'source' => 'meta', 'reference' => 'id'],
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

    $selectedVersion = $request->integer('version') ?: null;

    $fingerprintSubquery = TrafficLog::query()
      ->where('landing_id', $landingPage->id)
      ->when($selectedVersion, fn($q) => $q->where('landing_page_version_id', $selectedVersion))
      ->select('fingerprint');

    $leads = Lead::query()
      ->whereIn('fingerprint', $fingerprintSubquery)
      ->with(['leadFieldResponses.field', 'latestTrafficLog.landingPageVersion'])
      ->orderByDesc('created_at')
      ->paginate(25)
      ->withQueryString();

    $leads->through(fn(Lead $lead) => $this->transformLead($lead, $descriptors));

    return Inertia::render('landings/leads', [
      'landingPage' => $landingPage->only(['id', 'name', 'url']),
      'descriptors' => $descriptors,
      'leads' => $leads,
      'versions' => $versions,
      'selected_version' => $selectedVersion,
      'using_defaults' => $usingDefaults,
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
}
