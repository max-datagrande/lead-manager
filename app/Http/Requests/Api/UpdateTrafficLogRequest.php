<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para actualizar columnas de tracking de un traffic log ya registrado.
 *
 * La visita se identifica por `fingerprint` (mismo contrato que register/update
 * de leads). Hoy solo expone `s10` (click_id que llega async via cookie en el
 * trafico de Google Ads / YouTube). Columnas nuevas se agregan aca + en
 * `TrafficLogService::UPDATABLE_COLUMNS`.
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
   * Reglas de validacion.
   *
   * @return array<string, mixed>
   */
  public function rules(): array
  {
    return [
      'fingerprint' => 'required|string|max:255',
      // s10 (click_id) es el caso de uso principal y es requerido. Las demas
      // sub-params de tracking son opcionales: contrato plano y libre.
      's10' => 'required|string|max:255',
      's1' => 'nullable|string|max:255',
      's2' => 'nullable|string|max:255',
      's3' => 'nullable|string|max:255',
      's4' => 'nullable|string|max:255',
    ];
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
      's10.required' => 'The s10 field is required',
      's10.max' => 'The s10 field must not exceed 255 characters',
      's1.max' => 'The s1 field must not exceed 255 characters',
      's2.max' => 'The s2 field must not exceed 255 characters',
      's3.max' => 'The s3 field must not exceed 255 characters',
      's4.max' => 'The s4 field must not exceed 255 characters',
    ];
  }
}
