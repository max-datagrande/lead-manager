<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

/**
 * @method bool has(string $key)
 * @method mixed input(string $key = null, mixed $default = null)
 * @method void merge(array $input)
 */
class GeolocationRequest extends FormRequest
{
  /**
   * Determine if the user is authorized to make this request.
   *
   * @return bool
   */
  public function authorize()
  {
    return true; // La autorización se maneja en el middleware
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, mixed>
   */
  public function rules()
  {
    return [
      'ip' => ['required', 'string', 'ip']
    ];
  }

  /**
   * Get custom error messages for validator errors.
   *
   * @return array<string, string>
   */
  public function messages()
  {
    return [
      'ip.required' => 'The IP parameter is mandatory.',
      'ip.string' => 'The IP parameter must be a string.',
      'ip.ip' => 'The IP parameter must be a valid IP address (IPv4 or IPv6).'
    ];
  }

  /**
   * Get custom attributes for validator errors.
   *
   * @return array<string, string>
   */
  public function attributes()
  {
    return [
      'ip' => 'IP address'
    ];
  }

  /**
   * Handle a failed validation attempt.
   *
   * @param  \Illuminate\Contracts\Validation\Validator  $validator
   * @return void
   *
   * @throws \Illuminate\Http\Exceptions\HttpResponseException
   */
  protected function failedValidation(Validator $validator)
  {
    throw new HttpResponseException(
      response()->json([
        'error' => 'Validation failed',
        'message' => 'The provided parameters are invalid.',
        'errors' => $validator->errors(),
        'code' => 'VALIDATION_ERROR'
      ], 422)
    );
  }

  /**
   * Prepare the data for validation.
   *
   * @return void
   */
  protected function prepareForValidation()
  {
    if ($this->has('ip')) {
      $this->merge([
        'ip' => trim($this->input('ip'))
      ]);
    }
  }
}
