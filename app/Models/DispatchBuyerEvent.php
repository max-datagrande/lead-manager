<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchBuyerEvent extends Model
{
  public $timestamps = false;

  protected $fillable = [
    'lead_dispatch_id',
    'integration_id',
    'event',
    'reason',
    'detail',
    'created_at',
  ];

  protected $casts = [
    'created_at' => 'datetime',
  ];

  public function leadDispatch(): BelongsTo
  {
    return $this->belongsTo(LeadDispatch::class);
  }

  public function integration(): BelongsTo
  {
    return $this->belongsTo(Integration::class);
  }
}
