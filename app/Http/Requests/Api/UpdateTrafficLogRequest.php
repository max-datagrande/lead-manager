<?php

namespace App\Http\Requests\Api;

use App\Services\TrafficLog\TrafficLogService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para actualizar columnas de tracking de un traffic log ya registrado.
 *
 * La visita se identifica por `fingerprint` (mismo contrato que register/update
 * de leads) — unico campo requerido. El resto son las columnas actualizables de
 * `TrafficLogService::UPDATABLE_COLUMNS`, todas opcionales (contrato plano y
 * libre): se manda lo que llego tarde (s10/click_id, UTMs, etc.).
 */
class UpdateTrafficLogRequest extends FormRequest
{
  /**
   * Determina si el usuario esta autorizado para hacer esta request.
   */
  public function authorize(): bool
  {
    return true;
  }

  /**
   * Reglas de validacion. Derivadas del allowlist del servicio para mantener
   * una unica fuente de verdad: agregar una columna ahi la habilita aca.
   *
   * @return array<string, mixed>
   */
  public function rules(): array
  {
    $rules = [
      'fingerprint' => 'required|string|max:255',
    ];

    foreach (TrafficLogService::UPDATABLE_COLUMNS as $column) {
      $rules[$column] = 'nullable|string|max:255';
    }

    return $rules;
  }

  /**
   * Mensajes de error personalizados.
   *
   * @return array<string, string>
   */
  public function messages(): array
  {
    return [
      'fingerprint.required' => 'Fingerprint is required',
    ];
  }
}
