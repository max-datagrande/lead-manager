<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Field extends Model
{
  use HasFactory;

  protected $fillable = ['name', 'label', 'validation_rules', 'possible_values', 'is_array', 'user_id', 'updated_user_id'];

  protected $casts = [
    'possible_values' => 'array',
    'is_array' => 'boolean',
  ];
  protected static function booted(): void
  {
    $clearTokenCache = fn() => Cache::forget('internal_postback_tokens');

    static::creating(function (Field $field) {
      $field->user_id = Auth::id();
      $field->updated_user_id = Auth::id();
    });

    static::created($clearTokenCache);

    static::updating(function (Field $field) {
      $field->updated_user_id = Auth::id();
    });

    static::updated($clearTokenCache);
    static::deleted($clearTokenCache);
  }
  public function leadFieldResponses()
  {
    // Esto trae los campos de la tabla lead_field_responses cuando llamo desde el obejto field
    // Ejemplo: Fields::where('name', 'email')->first()->responses - Observa que al final responses es este método
    // Relación uno-a-muchos con LeadFieldResponses (opcional)
    return $this->hasMany(LeadFieldResponse::class, 'field_id');
  }

  public function leads()
  {
    return $this->belongsToMany(Lead::class, 'lead_field_responses')->withPivot('value')->withTimestamps();
  }
  public static function truncate()
  {
    DB::statement('TRUNCATE TABLE fields RESTART IDENTITY CASCADE');
  }
  public function forms()
  {
    return $this->belongsToMany(Form::class)->withTimestamps();
  }
}
