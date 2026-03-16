<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldMapping extends Model
{
  protected $fillable = ['integration_id', 'external_parameter', 'type', 'field_id', 'user_id', 'updated_user_id'];

  public function integration(): \Illuminate\Database\Eloquent\Relations\BelongsTo
  {
    return $this->belongsTo(Integration::class);
  }

  public function field(): \Illuminate\Database\Eloquent\Relations\BelongsTo
  {
    return $this->belongsTo(Field::class);
  }
}
