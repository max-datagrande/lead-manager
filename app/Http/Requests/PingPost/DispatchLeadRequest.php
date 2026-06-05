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
      // URL-driven workflow override metadata sent by the Catalyst SDK when the
      // landing was loaded with a `?workflow_id` that differs from the intended
      // workflow. Informational only — the dispatch already targets the effective
      // workflow via the route; this just drives the Slack override notification.
      'workflow_override' => ['nullable', 'array'],
      'workflow_override.id_intended' => ['required_with:workflow_override', 'string'],
      'workflow_override.id_effective' => ['required_with:workflow_override', 'string'],
    ];
  }
}
