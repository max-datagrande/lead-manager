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
      // lead_id is an explicit override; landings shouldn't need to pass it.
      // When absent, the controller resolves the lead from `fingerprint` via
      // LeadService::resolveLead — same pattern as the shareLead endpoint.
      'lead_id' => ['nullable', 'integer', Rule::exists('leads', 'id')],
      'fingerprint' => ['required', 'string', 'max:64'],
      'to' => ['nullable', 'string', 'max:120'],
      'channel' => ['nullable', 'string', Rule::in(['sms', 'call', 'email', 'whatsapp'])],
      'locale' => ['nullable', 'string', 'max:10'],
      'fields' => ['nullable', 'array'],
      // Matches shareLead semantics: create the lead if the traffic log exists
      // but no lead row does. Useful for one-shot landings that merge register
      // + challenge into a single round-trip.
      'create_on_miss' => ['nullable', 'boolean'],
    ];
  }
}
