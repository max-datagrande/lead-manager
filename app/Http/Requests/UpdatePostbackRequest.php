<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostbackRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  /**
   * @return array<string, mixed>
   */
  public function rules(): array
  {
    return [
      'name' => ['required', 'string', 'max:255'],
      'platform_id' => ['required', 'integer', 'exists:platforms,id'],
      'base_url' => ['required', 'url', 'max:2000'],
      'param_mappings' => ['required', 'array'],
      'param_mappings.*' => ['nullable', 'string', 'max:100'],
      'result_url' => ['nullable', 'string', 'max:2000'],
      'fire_mode' => ['required', 'string', Rule::in(['realtime', 'deferred'])],
      'is_active' => ['sometimes', 'boolean'],
      'is_public' => ['sometimes', 'boolean'],
    ];
  }
}
