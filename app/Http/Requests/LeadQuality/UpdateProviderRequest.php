<?php

namespace App\Http\Requests\LeadQuality;

use App\Enums\LeadQuality\LeadQualityProviderType;
use App\Enums\LeadQuality\ProviderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProviderRequest extends FormRequest
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
    $providerId = $this->route('provider')?->id;

    return [
      'name' => ['required', 'string', 'max:120', Rule::unique('lead_quality_providers', 'name')->ignore($providerId)],
      'type' => ['required', 'string', Rule::in(array_column(LeadQualityProviderType::cases(), 'value'))],
      'status' => ['required', 'string', Rule::in(array_column(ProviderStatus::cases(), 'value'))],
      'is_enabled' => ['sometimes', 'boolean'],
      'environment' => ['required', 'string', Rule::in(['production', 'sandbox', 'test'])],
      'credentials' => ['nullable', 'array'],
      'credentials.*' => ['nullable', 'string', 'max:2000'],
      'settings' => ['nullable', 'array'],
      'friendly_name' => ['nullable', 'string', 'max:30'],
      'notes' => ['nullable', 'string', 'max:2000'],
    ];
  }
}
