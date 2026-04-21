<?php

namespace App\Http\Requests\LeadQuality;

use Illuminate\Foundation\Http\FormRequest;

class ProviderOtpTestVerifyRequest extends FormRequest
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
      'to' => ['required', 'string', 'min:6', 'max:120'],
      'code' => ['required', 'string', 'min:4', 'max:12'],
    ];
  }
}
