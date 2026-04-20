<?php

namespace App\Http\Controllers\LeadQuality;

use App\Enums\LeadQuality\ValidationLogStatus;
use App\Http\Controllers\Controller;
use App\Models\ExternalServiceRequest;
use App\Models\Integration;
use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use App\Traits\DatatableTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ValidationLogController extends Controller
{
  use DatatableTrait;

  public function index(Request $request): Response
  {
    $query = LeadQualityValidationLog::query()->with(['rule:id,name,validation_type', 'provider:id,name,type', 'integration:id,name']);

    $table = $this->processDatatableQuery(
      query: $query,
      request: $request,
      searchableColumns: ['fingerprint', 'challenge_reference', 'message'],
      filterConfig: [
        'status' => ['type' => 'exact'],
        'validation_rule_id' => ['type' => 'exact'],
        'integration_id' => ['type' => 'exact'],
        'provider_id' => ['type' => 'exact'],
        'fingerprint' => ['type' => 'like'],
        'from_date' => ['type' => 'from_date', 'column' => 'created_at'],
        'to_date' => ['type' => 'to_date', 'column' => 'created_at'],
      ],
      allowedSort: ['id', 'status', 'attempts_count', 'started_at', 'resolved_at', 'created_at'],
      defaultSort: 'created_at:desc',
    );

    $table['rows']->getCollection()->transform(fn(LeadQualityValidationLog $log) => $this->serializeRow($log));

    return Inertia::render('lead-quality/validation-logs/index', [
      'rows' => $table['rows'],
      'meta' => $table['meta'],
      'state' => $table['state'],
      'data' => [
        'status_options' => ValidationLogStatus::toArray(),
        'rules' => LeadQualityValidationRule::query()
          ->orderBy('name')
          ->get(['id', 'name'])
          ->map(fn(LeadQualityValidationRule $r) => ['id' => $r->id, 'name' => $r->name])
          ->all(),
        'providers' => LeadQualityProvider::query()
          ->orderBy('name')
          ->get(['id', 'name'])
          ->map(fn(LeadQualityProvider $p) => ['id' => $p->id, 'name' => $p->name])
          ->all(),
        'buyers' => Integration::query()
          ->whereIn('type', ['ping-post', 'post-only'])
          ->orderBy('name')
          ->get(['id', 'name'])
          ->map(fn(Integration $i) => ['id' => $i->id, 'name' => $i->name])
          ->all(),
      ],
    ]);
  }

  public function show(LeadQualityValidationLog $log): JsonResponse
  {
    $log->load([
      'rule:id,name,validation_type,status,is_enabled',
      'provider:id,name,type,status,is_enabled',
      'integration:id,name,type',
      'lead:id,fingerprint',
      'leadDispatch:id,dispatch_uuid,status,workflow_id',
    ]);

    return response()->json([
      'log' => $this->serializeDetail($log),
    ]);
  }

  public function technical(LeadQualityValidationLog $log): JsonResponse
  {
    $requests = ExternalServiceRequest::query()
      ->where('loggable_type', LeadQualityValidationLog::class)
      ->where('loggable_id', $log->id)
      ->orderBy('requested_at')
      ->get()
      ->map(
        fn(ExternalServiceRequest $req) => [
          'id' => $req->id,
          'operation' => $req->operation,
          'service_name' => $req->service_name,
          'request_method' => $req->request_method,
          'request_url' => $req->request_url,
          'request_headers' => $req->request_headers,
          'request_body' => $req->request_body,
          'response_status_code' => $req->response_status_code,
          'response_headers' => $req->response_headers,
          'response_body' => $req->response_body,
          'status' => $req->status,
          'error_message' => $req->error_message,
          'duration_ms' => $req->duration_ms,
          'requested_at' => $req->requested_at,
          'responded_at' => $req->responded_at,
        ],
      );

    return response()->json([
      'requests' => $requests,
    ]);
  }

  /**
   * @return array<string, mixed>
   */
  private function serializeRow(LeadQualityValidationLog $log): array
  {
    return [
      'id' => $log->id,
      'status' => $log->status->value,
      'status_label' => $log->status->label(),
      'result' => $log->result,
      'attempts_count' => $log->attempts_count,
      'fingerprint' => $log->fingerprint,
      'challenge_reference' => $log->challenge_reference,
      'message' => $log->message,
      'started_at' => $log->started_at,
      'resolved_at' => $log->resolved_at,
      'expires_at' => $log->expires_at,
      'created_at' => $log->created_at,
      'rule' => $log->rule
        ? [
          'id' => $log->rule->id,
          'name' => $log->rule->name,
          'validation_type' => $log->rule->validation_type?->value,
        ]
        : null,
      'provider' => $log->provider
        ? [
          'id' => $log->provider->id,
          'name' => $log->provider->name,
          'type' => $log->provider->type?->value,
        ]
        : null,
      'buyer' => $log->integration
        ? [
          'id' => $log->integration->id,
          'name' => $log->integration->name,
        ]
        : null,
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function serializeDetail(LeadQualityValidationLog $log): array
  {
    return array_merge($this->serializeRow($log), [
      'context' => $log->context,
      'lead' => $log->lead
        ? [
          'id' => $log->lead->id,
          'fingerprint' => $log->lead->fingerprint,
        ]
        : null,
      'lead_dispatch' => $log->leadDispatch
        ? [
          'id' => $log->leadDispatch->id,
          'dispatch_uuid' => $log->leadDispatch->dispatch_uuid,
          'status' => $log->leadDispatch->status?->value,
        ]
        : null,
      'rule_detail' => $log->rule
        ? [
          'id' => $log->rule->id,
          'name' => $log->rule->name,
          'validation_type' => $log->rule->validation_type?->value,
          'status' => $log->rule->status?->value,
          'is_enabled' => $log->rule->is_enabled,
        ]
        : null,
      'provider_detail' => $log->provider
        ? [
          'id' => $log->provider->id,
          'name' => $log->provider->name,
          'type' => $log->provider->type?->value,
          'status' => $log->provider->status?->value,
          'is_enabled' => $log->provider->is_enabled,
        ]
        : null,
    ]);
  }
}
