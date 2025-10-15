<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

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
      self::PROCESSED => false, // No se puede cambiar una vez procesado
      self::FAILED => in_array($newStatus, [self::PENDING]), // Se puede reintentar
      self::SKIPPED => false, // No se puede cambiar una vez saltado
    };
  }

  public static function toArray(): array
  {
    return array_map(fn($case) => [
      'value' => $case->value,
      'label' => $case->label(),
      'iconName' => $case->icon(),
    ], self::cases());
  }
}

class Postback extends Model
{
  protected $table = 'postbacks';
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
    'processed_at'
  ];

  protected $casts = [
    'status' => PostbackStatus::class,
    'payout' => 'decimal:2',
    'processed_at' => 'datetime',
    'response_data' => 'array'
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
  public function markAsSkipped()
  {
    $this->update([
      'status' => PostbackStatus::SKIPPED,
      'processed_at' => now(),
    ]);
  }

  // Métodos de utilidad
  public function markAsProcessed($responseData = null)
  {
    $this->update([
      'status' => PostbackStatus::PROCESSED,
      'processed_at' => now(),
      'response_data' => $responseData
    ]);
  }

  public function markAsFailed(?string $reason = null, $responseData = null)
  {
    $this->update([
      'status' => PostbackStatus::FAILED,
      'message' => $reason,
      'processed_at' => now(),
      'response_data' => $responseData
    ]);
  }

  public function isPending()
  {
    return $this->status === PostbackStatus::PENDING;
  }

  public function isProcessed()
  {
    return $this->status === PostbackStatus::PROCESSED;
  }

  public function isFailed()
  {
    return $this->status === PostbackStatus::FAILED;
  }

  public function isSkipped()
  {
    return $this->status === PostbackStatus::SKIPPED;
  }

  // Accessor para formatear el payout
  public function getFormattedPayoutAttribute()
  {
    return $this->currency . ' ' . number_format($this->payout, 2);
  }

  // Mutator para asegurar que el currency esté en mayúsculas
  public function setCurrencyAttribute($value)
  {
    $this->attributes['currency'] = strtoupper($value);
  }

  // Métodos estáticos para acceder fácilmente a los estados
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
