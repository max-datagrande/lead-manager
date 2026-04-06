<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StorePerformanceMetricRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  /**
   * @return array<string, string>
   */
  public function rules(): array
  {
    return [
      'host' => 'required|string|max:255',
      'load_time_ms' => 'required|integer|min:0|max:60000',
      'fingerprint' => 'nullable|string|max:255',
    ];
  }
}
