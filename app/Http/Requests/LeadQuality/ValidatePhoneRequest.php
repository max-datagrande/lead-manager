<?php

namespace App\Http\Requests\LeadQuality;

use Illuminate\Foundation\Http\FormRequest;

class ValidatePhoneRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  /**
   * Validator is workflow-agnostic by design: there is no `workflow_id` /
   * `buyer_id` here. The provider Melissa is resolved globally by the
   * service.
   *
   * @return array<string, mixed>
   */
  public function rules(): array
  {
    return [
      'fingerprint' => ['required', 'string', 'max:64'],
      'phone' => ['required', 'string', 'max:30'],
      'country' => ['nullable', 'string', 'size:2'],
    ];
  }
}
