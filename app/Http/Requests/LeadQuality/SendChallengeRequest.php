<?php

namespace App\Http\Requests\LeadQuality;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendChallengeRequest extends FormRequest
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
      'workflow_id' => ['required', 'integer', Rule::exists('workflows', 'id')],
      'lead_id' => ['required', 'integer', Rule::exists('leads', 'id')],
      'fingerprint' => ['required', 'string', 'max:64'],
      'to' => ['nullable', 'string', 'max:120'],
      'channel' => ['nullable', 'string', Rule::in(['sms', 'call', 'email', 'whatsapp'])],
      'locale' => ['nullable', 'string', 'max:10'],
      'fields' => ['nullable', 'array'],
    ];
  }
}
