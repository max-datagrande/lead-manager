<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OfferwallTesterExecuteRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  /**
   * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    return [
      'integration_id' => 'required|integer|exists:integrations,id',
      'mix_log_id' => 'required|integer|exists:offerwall_mix_logs,id',
      'lead_id' => 'required|integer|exists:leads,id',
      'cptype' => 'nullable|string|max:100',
    ];
  }
}
