<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
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
   * @return Leads|null Modelo del lead con relaciones cargadas, o `null` si no existe.
   *
   * @example
   * $lead = Leads::getLeadResponses('abc123');
   * foreach ($lead->leadFieldResponses as $response) {
   *     echo $response->fields->name . ": " . $response->value;
   * }
   */
  public static function getLeadResponses($fingerprint)
  {
    return self::with(['leadFieldResponses.fields'])
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
  public function fields()             // acceso directo a los Field
  {
    return $this->belongsToMany(Field::class, 'lead_field_responses')
      ->withPivot('value')
      ->withTimestamps();
  }
  public function sales()
  {
    return $this->hasMany(Sale::class, 'fingerprint', 'fingerprint');
  }

  /**
   * Relation to the lead's traffic logs by fingerprint.
   */
  public function trafficLogs()
  {
    return $this->hasMany(TrafficLog::class, 'fingerprint', 'fingerprint');
  }

  /**
   * Retrieve the latest known host from the traffic logs.
   */
  public function getHostAttribute()
  {
    return $this->trafficLogs()->latest('visit_date')->value('host');
  }

    /**
     * Relación con logs de conversiones
     */
    public function conversionLogs()
    {
        return $this->hasMany(ConversionLog::class, 'fingerprint', 'fingerprint');
    }
}

