<?php

namespace App\Http\Requests\LeadQuality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProviderOtpTestSendRequest extends FormRequest
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
      'channel' => ['nullable', 'string', Rule::in(['sms', 'call', 'email', 'whatsapp'])],
      'locale' => ['nullable', 'string', 'max:10'],
    ];
  }
}
