<?php

namespace App\Http\Requests\LeadQuality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidatePhoneRequest extends FormRequest
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
      'fingerprint' => ['required', 'string', 'max:64'],
      'phone' => ['required', 'string', 'max:30'],
      'country' => ['nullable', 'string', 'size:2'],
      // Optional, only used for trace/log readability — does not influence
      // provider resolution (validation is workflow-agnostic by design).
      'workflow_id' => ['nullable', 'integer', Rule::exists('workflows', 'id')],
    ];
  }
}
