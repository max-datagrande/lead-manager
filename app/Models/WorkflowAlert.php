<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowAlert extends Model
{
  protected $fillable = ['workflow_id', 'alert_channel_id', 'is_active'];

  protected $casts = [
    'is_active' => 'boolean',
  ];

  public function workflow(): BelongsTo
  {
    return $this->belongsTo(Workflow::class);
  }

  public function alertChannel(): BelongsTo
  {
    return $this->belongsTo(AlertChannel::class);
  }
}
