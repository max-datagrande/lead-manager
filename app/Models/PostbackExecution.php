<?php

namespace App\Models;

use App\Enums\ExecutionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class PostbackExecution extends Model
{
    protected $fillable = [
        'execution_uuid',
        'postback_id',
        'status',
        'inbound_params',
        'resolved_tokens',
        'outbound_url',
        'ip_address',
        'user_agent',
        'attempts',
        'max_attempts',
        'next_retry_at',
        'dispatched_at',
        'completed_at',
        'idempotency_key',
    ];

    protected $casts = [
        'status' => ExecutionStatus::class,
        'inbound_params' => 'array',
        'resolved_tokens' => 'array',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'next_retry_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            $model->execution_uuid ??= (string) Str::uuid();
        });
    }

    // --- Relationships ---

    public function postback(): BelongsTo
    {
        return $this->belongsTo(Postback::class);
    }

    public function dispatchLogs(): HasMany
    {
        return $this->hasMany(PostbackDispatchLog::class, 'execution_id');
    }

    public function latestDispatchLog(): HasOne
    {
        return $this->hasOne(PostbackDispatchLog::class, 'execution_id')->latestOfMany();
    }

    // --- Scopes ---

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ExecutionStatus::PENDING);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', ExecutionStatus::FAILED);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRetryable(Builder $query): Builder
    {
        return $query
            ->where('status', ExecutionStatus::FAILED)
            ->whereColumn('attempts', '<', 'max_attempts')
            ->where('next_retry_at', '<=', now());
    }

    // --- Status helpers ---

    public function markAsDispatching(): void
    {
        $this->update([
            'status' => ExecutionStatus::DISPATCHING,
            'dispatched_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => ExecutionStatus::COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(?string $reason = null): void
    {
        $delaySeconds = min(60 * (2 ** ($this->attempts - 1)), 3600);

        $this->update([
            'status' => ExecutionStatus::FAILED,
            'next_retry_at' => $this->attempts < $this->max_attempts ? now()->addSeconds($delaySeconds) : null,
        ]);
    }

    public function markAsSkipped(?string $reason = null): void
    {
        $this->update([
            'status' => ExecutionStatus::SKIPPED,
            'completed_at' => now(),
        ]);
    }

    public function incrementAttempt(): void
    {
        $this->increment('attempts');
    }

    // --- Idempotency ---

    /**
     * @param  array<string, string>  $params
     */
    public static function generateIdempotencyKey(int $postbackId, array $params): string
    {
        ksort($params);

        return hash('sha256', $postbackId.'|'.json_encode($params));
    }
}
