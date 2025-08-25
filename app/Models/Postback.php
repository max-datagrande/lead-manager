<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Postback extends Model
{
  protected $table = 'postbacks';
  protected $primaryKey = 'id';
  public $timestamps = true;
  protected $fillable = [
    'id',
    'vendor',
    'clid',
    'payout',
    'txid',
    'currency',
    'event',
    'offer_id',
    'status',
    'response_data',
    'processed_at'
  ];

  protected $casts = [
    'payout' => 'decimal:2',
    'processed_at' => 'datetime',
    'response_data' => 'array'
  ];

  // Constantes para los estados
  const STATUS_PENDING = 'pending';
  const STATUS_PROCESSED = 'processed';
  const STATUS_FAILED = 'failed';

  // Scopes
  public function scopePending($query)
  {
    return $query->where('status', self::STATUS_PENDING);
  }

  public function scopeProcessed($query)
  {
    return $query->where('status', self::STATUS_PROCESSED);
  }

  public function scopeFailed($query)
  {
    return $query->where('status', self::STATUS_FAILED);
  }

  public function scopeByVendor($query, $vendor)
  {
    return $query->where('vendor', $vendor);
  }

  // Métodos de utilidad
  public function markAsProcessed($responseData = null)
  {
    $this->update([
      'status' => self::STATUS_PROCESSED,
      'processed_at' => now(),
      'response_data' => $responseData
    ]);
  }

  public function markAsFailed($responseData = null)
  {
    $this->update([
      'status' => self::STATUS_FAILED,
      'processed_at' => now(),
      'response_data' => $responseData
    ]);
  }

  public function isPending()
  {
    return $this->status === self::STATUS_PENDING;
  }

  public function isProcessed()
  {
    return $this->status === self::STATUS_PROCESSED;
  }

  public function isFailed()
  {
    return $this->status === self::STATUS_FAILED;
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
}
