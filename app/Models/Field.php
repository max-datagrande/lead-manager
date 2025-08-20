<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Field extends Model
{
  protected $fillable = [
    'name',
    'label',
    'validation_rules',
    'user_id',
    'updated_user_id',
  ];
  protected static function booted(): void
  {
    static::creating(function (Field $field) {
      $field->user_id         = Auth::id();
      $field->updated_user_id = Auth::id();
    });

    static::updating(function (Field $field) {
      $field->updated_user_id = Auth::id();
    });
  }
  public function leadFieldResponses() // Esto trae los campos de la tabla lead_field_responses cuando llamo desde el obejto field
  // Ejemplo: Fields::where('name', 'email')->first()->responses - Observa que al final responses es este método
  {
    // Relación uno-a-muchos con LeadFieldResponses (opcional)
    return $this->hasMany(LeadFieldResponse::class, 'field_id');
  }

  public function leads()
  {
    return $this->belongsToMany(Lead::class, 'lead_field_responses')
      ->withPivot('value')
      ->withTimestamps();
  }

  public function forms()
  {
    return $this->belongsToMany(Form::class)
      ->withTimestamps();
  }
}
