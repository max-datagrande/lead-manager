<?php

namespace App\Http\Requests\LeadQuality;

use Illuminate\Foundation\Http\FormRequest;

class ProviderPhoneValidateTestRequest extends FormRequest
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
      'phone' => ['required', 'string', 'min:6', 'max:30'],
      'country' => ['nullable', 'string', 'size:2'],
    ];
  }
}
