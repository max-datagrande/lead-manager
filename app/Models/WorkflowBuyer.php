<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class WorkflowBuyer extends Pivot
{
  protected $table = 'workflow_buyers';

  public $incrementing = true;

  protected $fillable = ['workflow_id', 'integration_id', 'position', 'is_fallback', 'buyer_group', 'is_active'];

  protected $casts = [
    'is_fallback' => 'boolean',
    'is_active' => 'boolean',
  ];

  public function workflow(): BelongsTo
  {
    return $this->belongsTo(Workflow::class);
  }

  public function integration(): BelongsTo
  {
    return $this->belongsTo(Integration::class);
  }
}
