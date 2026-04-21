<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Generic technical log of HTTP calls to any external service, from any module.
 *
 * Not specific to Lead Quality — modules discriminate via `module` + `service_name`.
 * The polymorphic `loggable` points back to whatever business entity originated
 * the call (e.g., LeadQualityValidationLog). Any module can define a `morphMany`
 * against this model to attach its own request/response trail.
 */
class ExternalServiceRequest extends Model
{
  use HasFactory;

  protected $table = 'external_service_requests';

  protected $fillable = [
    'loggable_type',
    'loggable_id',
    'module',
    'service_name',
    'service_id',
    'operation',
    'request_method',
    'request_url',
    'request_headers',
    'request_body',
    'response_status_code',
    'response_headers',
    'response_body',
    'status',
    'error_message',
    'duration_ms',
    'requested_at',
    'responded_at',
  ];

  protected $casts = [
    'request_headers' => 'array',
    'request_body' => 'array',
    'response_headers' => 'array',
    'response_body' => 'array',
    'response_status_code' => 'integer',
    'duration_ms' => 'integer',
    'service_id' => 'integer',
    'requested_at' => 'datetime',
    'responded_at' => 'datetime',
  ];

  public function loggable(): MorphTo
  {
    return $this->morphTo();
  }
}
