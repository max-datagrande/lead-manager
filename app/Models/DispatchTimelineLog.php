<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchTimelineLog extends Model
{
  protected $fillable = [
    'fingerprint',
    'lead_dispatch_id',
    'event',
    'message',
    'context',
    'logged_at',
  ];

  protected $casts = [
    'context' => 'array',
    'logged_at' => 'datetime',
  ];

  public function leadDispatch(): BelongsTo
  {
    return $this->belongsTo(LeadDispatch::class);
  }
}
