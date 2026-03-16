<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

enum PostbackStatus: string
{
    case PENDING = 'pending';
    case PROCESSED = 'processed';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSED => 'Processed',
            self::FAILED => 'Failed',
            self::SKIPPED => 'Skipped',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'Badge',
            self::PROCESSED => 'BadgeCheck',
            self::FAILED => 'BadgeAlert',
            self::SKIPPED => 'BadgeMinus',
        };
    }

    public function canTransitionTo(PostbackStatus $newStatus): bool
    {
        return match ($this) {
            self::PENDING => in_array($newStatus, [self::PROCESSED, self::FAILED]),
            self::PROCESSED => false,
            self::FAILED => in_array($newStatus, [self::PENDING]),
            self::SKIPPED => false,
        };
    }

    public static function toArray(): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'iconName' => $case->icon(),
        ], self::cases());
    }
}

class PostbackQueue extends Model
{
    protected $table = 'postback_queue';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'id',
        'vendor',
        'click_id',
        'payout',
        'transaction_id',
        'currency',
        'event',
        'offer_id',
        'status',
        'message',
        'response_data',
        'processed_at',
    ];

    protected $casts = [
        'status' => PostbackStatus::class,
        'payout' => 'decimal:2',
        'processed_at' => 'datetime',
        'response_data' => 'array',
    ];

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', PostbackStatus::PENDING);
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', PostbackStatus::PROCESSED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', PostbackStatus::FAILED);
    }

    public function scopeSkipped($query)
    {
        return $query->where('status', PostbackStatus::SKIPPED);
    }

    public function scopeByVendor($query, $vendor)
    {
        return $query->where('vendor', $vendor);
    }

    public function markAsSkipped(): void
    {
        $this->update([
            'status' => PostbackStatus::SKIPPED,
            'processed_at' => now(),
        ]);
    }

    public function markAsProcessed($responseData = null): void
    {
        $this->update([
            'status' => PostbackStatus::PROCESSED,
            'processed_at' => now(),
            'response_data' => $responseData,
        ]);
    }

    public function markAsFailed(?string $reason = null, $responseData = null): void
    {
        $this->update([
            'status' => PostbackStatus::FAILED,
            'message' => $reason,
            'processed_at' => now(),
            'response_data' => $responseData,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === PostbackStatus::PENDING;
    }

    public function isProcessed(): bool
    {
        return $this->status === PostbackStatus::PROCESSED;
    }

    public function isFailed(): bool
    {
        return $this->status === PostbackStatus::FAILED;
    }

    public function isSkipped(): bool
    {
        return $this->status === PostbackStatus::SKIPPED;
    }

    public function getFormattedPayoutAttribute(): string
    {
        return $this->currency.' '.number_format($this->payout, 2);
    }

    public function setCurrencyAttribute($value): void
    {
        $this->attributes['currency'] = strtoupper($value);
    }

    public static function statusPending(): PostbackStatus
    {
        return PostbackStatus::PENDING;
    }

    public static function statusProcessed(): PostbackStatus
    {
        return PostbackStatus::PROCESSED;
    }

    public static function statusFailed(): PostbackStatus
    {
        return PostbackStatus::FAILED;
    }

    public static function statusSkipped(): PostbackStatus
    {
        return PostbackStatus::SKIPPED;
    }
}
