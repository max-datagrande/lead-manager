<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
  use HasFactory;

  protected $table = 'leads';

  protected $primaryKey = 'id';

  public $incrementing = true;

  // Allow all attributes to be mass assignable so dynamic lead
  // properties sent via the API can be stored without explicitly
  // listing every possible field in the fillable array.
  protected $guarded = [];

  /**
   * Obtiene un lead con sus respuestas y campos relacionados mediante el fingerprint.
   * - Carga las respuestas asociadas al lead (`leadFieldResponses`).
   * - Carga los campos relacionados a cada respuesta (`fields`).
   *
   * @param  string  $fingerprint  Identificador único del lead.
   * @return Lead|null Modelo del lead con relaciones cargadas, o `null` si no existe.
   *
   * @example
   * $lead = Leads::getLeadResponses('abc123');
   * foreach ($lead->leadFieldResponses as $response) {
   *     echo $response->fields->name . ": " . $response->value;
   * }
   */
  public static function getLeadWithResponses($fingerprint)
  {
    return self::with(['leadFieldResponses.field'])
      ->where('fingerprint', $fingerprint)
      ->first();
  }

  /**
   * Define la relación uno-a-muchos con las respuestas del lead (`LeadFieldResponses`).
   * - Un lead puede tener múltiples respuestas asociadas.
   * - La clave foránea en `lead_field_responses` es `lead_id`.
   *
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   *
   * @example
   * $lead = Leads::find(1);
   * $responses = $lead->leadFieldResponses; // Colección de respuestas
   */
  public function leadFieldResponses()
  {
    // Relación uno-a-muchos con LeadFieldResponses
    return $this->hasMany(LeadFieldResponse::class, 'lead_id');
  }

  public function fields()
  {
    // acceso directo a los Field
    return $this->belongsToMany(Field::class, 'lead_field_responses')->withPivot('value')->withTimestamps();
  }

  /**
   * Relation to the lead's traffic logs by fingerprint.
   */
  public function trafficLogs()
  {
    return $this->hasMany(TrafficLog::class, 'fingerprint', 'fingerprint');
  }

  /**
   * Convenience hasOne pointing to the most recent traffic log by created_at.
   * `visit_date` is a DATE column (no time component) so it's not granular enough
   * to disambiguate visits within the same day.
   */
  public function latestTrafficLog()
  {
    return $this->hasOne(TrafficLog::class, 'fingerprint', 'fingerprint')->latestOfMany('created_at');
  }

  /**
   * Retrieve the latest known host from the traffic logs.
   */
  public function getHostAttribute()
  {
    return $this->trafficLogs()->latest('visit_date')->value('host');
  }
}
