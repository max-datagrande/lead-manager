<?php

namespace App\Http\Requests\LeadQuality;

use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateValidationRuleRequest extends FormRequest
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
      'name' => ['required', 'string', 'max:140'],
      'validation_type' => ['required', 'string', Rule::in(array_column(ValidationType::cases(), 'value'))],
      'provider_id' => ['required', 'integer', Rule::exists('lead_quality_providers', 'id')],
      'status' => ['required', 'string', Rule::in(array_column(RuleStatus::cases(), 'value'))],
      'is_enabled' => ['sometimes', 'boolean'],
      'description' => ['nullable', 'string', 'max:2000'],
      'settings' => ['nullable', 'array'],
      'settings.channel' => ['nullable', 'string', Rule::in(['sms', 'call', 'email', 'whatsapp'])],
      'settings.otp_length' => ['nullable', 'integer', 'min:4', 'max:10'],
      'settings.ttl' => ['nullable', 'integer', 'min:60', 'max:3600'],
      'settings.max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
      'settings.validity_window' => ['nullable', 'integer', 'min:1', 'max:1440'],
      'settings.required_score' => ['nullable', 'integer', 'min:0', 'max:100'],
      'settings.sync_check' => ['nullable', 'boolean'],
      'priority' => ['sometimes', 'integer', 'min:0', 'max:65535'],
      'buyer_ids' => ['nullable', 'array'],
      'buyer_ids.*' => ['integer', Rule::exists('integrations', 'id')],
    ];
  }
}
