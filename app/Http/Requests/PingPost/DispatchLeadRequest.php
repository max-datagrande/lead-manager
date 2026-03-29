<?php

namespace App\Http\Requests\PingPost;

use Illuminate\Foundation\Http\FormRequest;

class DispatchLeadRequest extends FormRequest
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
      'fingerprint' => ['required_without:lead_id', 'nullable', 'string', 'max:255'],
      'lead_id' => ['required_without:fingerprint', 'nullable', 'integer', 'exists:leads,id'],
      'fields' => ['nullable', 'array'],
      'create_on_miss' => ['nullable', 'boolean'],
    ];
  }
}
