<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para validar datos de traffic log
 *
 * Valida los datos obligatorios y opcionales enviados desde el cliente
 * para el registro de tráfico web
 *
 * @method mixed input(string $key = null, mixed $default = null)
 * @method bool has(string $key)
 * @method void merge(array $input)
 * @method mixed only(array|mixed $keys)
 * @method bool boolean(string $key = null, bool $default = false)
 * @method string string(string $key, string $default = '')
 * @method string ip()
 */
class StoreTrafficLogRequest extends FormRequest
{
  /**
   * Determina si el usuario está autorizado para hacer esta request
   */
  public function authorize(): bool
  {
    return true;
  }

  /**
   * Reglas de validación para los datos de traffic log
   *
   * @return array<string, mixed>
   */
  public function rules(): array
  {
    return [
      // Datos obligatorios del cliente
      'user_agent' => 'required|string|min:10|max:500',
      'referer' => 'nullable|present|url|max:2000', // Referrer de la landing page
      'query_params' => 'present|array', // Objeto con parámetros de la URL

      //Page path visited
      'current_page' => 'required|string|max:2000',

      // Control de detección de bot (opcional)
      'is_bot' => 'nullable|boolean', // Si no se especifica, usamos BotDetectorService

      // Datos opcionales - si no se envían, se buscan en query_params
      's1' => 'nullable|string|max:255',
      's2' => 'nullable|string|max:255',
      's3' => 'nullable|string|max:255',
      's4' => 'nullable|string|max:255',
      's10' => 'nullable|string|max:255',
    ];
  }

  /**
   * Mensajes de error personalizados para las validaciones
   *
   * @return array<string, string>
   */
  public function messages(): array
  {
    return [
      'user_agent.required' => 'User agent is required',
      'user_agent.min' => 'The user agent must be at least 10 characters long',
      'user_agent.max' => 'The user agent must not exceed 500 characters',
      'referer.url' => 'The original referrer must be a valid URL',
      'referer.max' => 'The original referrer must not exceed 2000 characters',
      'query_params.required' => 'Query parameters are required',
      'query_params.array' => 'Query parameters must be an object',
      'current_page.required' => 'Path visited is required',
      'current_page.max' => 'Path visited must not exceed 2000 characters',
      'is_bot.boolean' => 'The is_bot field must be true or false',
      's1.max' => 'The s1 field must not exceed 255 characters',
      's2.max' => 'The s2 field must not exceed 255 characters',
      's3.max' => 'The s3 field must not exceed 255 characters',
      's4.max' => 'The s4 field must not exceed 255 characters',
      's10.max' => 'The s10 field must not exceed 255 characters',
    ];
  }

  /**
   * Prepara los datos para validación
   *
   * Extrae s1-s4 de query_params si no se proporcionaron directamente
   */
  protected function prepareForValidation(): void
  {
    // Obtener query_params del request
    $queryParams = $this->input('query_params', []);

    // Asegurar que query_params sea un array
    if (!is_array($queryParams)) {
      $queryParams = [];
    }

    $dataToMerge = [];

    // Si s1-s4 no se proporcionaron directamente, intentar extraerlos de query_params
    if (!$this->has('s1') && isset($queryParams['s1'])) {
      $dataToMerge['s1'] = $queryParams['s1'];
    }

    if (!$this->has('s2') && isset($queryParams['s2'])) {
      $dataToMerge['s2'] = $queryParams['s2'];
    }

    if (!$this->has('s3') && isset($queryParams['s3'])) {
      $dataToMerge['s3'] = $queryParams['s3'];
    }

    if (!$this->has('s4') && isset($queryParams['s4'])) {
      $dataToMerge['s4'] = $queryParams['s4'];
    }

    if (!$this->has('s10') && isset($queryParams['s10'])) {
      $dataToMerge['s10'] = $queryParams['s10'];
    }

    // Hacer merge de todos los datos de una vez
    if (!empty($dataToMerge)) {
      $this->merge($dataToMerge);
    }
  }
}
