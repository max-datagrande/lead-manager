<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostbackApiRequests extends Model
{
    protected $fillable = [
        'service',
        'endpoint',
        'method',
        'request_data',
        'response_data',
        'status_code',
        'error_message',
        'response_time_ms',
        'request_id',
        'related_type',
        'postback_id',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'status_code' => 'integer',
        'response_time_ms' => 'integer',
        'postback_id' => 'integer',
    ];

    // Constantes para servicios
    const SERVICE_NATURAL_INTELLIGENCE = 'natural_intelligence';
    const SERVICE_OTHER_API = 'other_api';

    // Constantes para tipos relacionados
    const RELATED_TYPE_REPORT = 'report';
    const RELATED_TYPE_RECONCILIATION = 'reconciliation';
    const RELATED_TYPE_POSTBACK_REDIRECT = 'postback_redirect';
    const RELATED_TYPE_SEARCH_PAYOUT = 'search_payout';
    const RELATED_TYPE_SYNC = 'sync_job';
    //Relations

    public function postback()
    {
        return $this->belongsTo(Postback::class, 'postback_id');
    }

    // Scopes
    public function scopeByService($query, $service)
    {
        return $query->where('service', $service);
    }

    public function scopeByRelated($query, $type, $id)
    {
        return $query->where('related_type', $type)->where('related_id', $id);
    }

    public function scopeSuccessful($query)
    {
        return $query->whereBetween('status_code', [200, 299]);
    }

    public function scopeFailed($query)
    {
        return $query->where(function ($q) {
            $q->where('status_code', '<', 200)
              ->orWhere('status_code', '>=', 400)
              ->orWhereNull('status_code');
        });
    }

    // Métodos de utilidad
    public function isSuccessful(): bool
    {
        return $this->status_code >= 200 && $this->status_code < 300;
    }

    public function isFailed(): bool
    {
        return !$this->isSuccessful();
    }

    // Método estático para crear registro de petición
    public static function logRequest(
        string $service,
        string $endpoint,
        string $method = 'GET',
        ?array $requestData = null,
        ?string $relatedType = null,
        ?int $relatedId = null
    ): self {
        return self::create([
            'service' => $service,
            'endpoint' => $endpoint,
            'method' => $method,
            'request_data' => $requestData,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'request_id' => uniqid('req_'),
        ]);
    }

    // Método para actualizar con respuesta
    public function updateWithResponse(
        ?array $responseData = null,
        ?int $statusCode = null,
        ?string $errorMessage = null,
        ?int $responseTimeMs = null
    ): bool {
        return $this->update([
            'response_data' => $responseData,
            'status_code' => $statusCode,
            'error_message' => $errorMessage,
            'response_time_ms' => $responseTimeMs,
        ]);
    }
}
