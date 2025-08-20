<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadFieldResponse extends Model
{
  protected $fillable = ['lead_id', 'field_id', 'value', 'fingerprint'];
  protected $guarded = [];

  public function lead()
  {
    return $this->belongsTo(Lead::class);
  }

  public function field()
  {
    return $this->belongsTo(Field::class);
  }
}
