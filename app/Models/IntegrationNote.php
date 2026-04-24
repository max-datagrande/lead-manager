<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationNote extends Model
{
  protected $fillable = ['integration_id', 'content'];

  public function integration(): BelongsTo
  {
    return $this->belongsTo(Integration::class);
  }
}
