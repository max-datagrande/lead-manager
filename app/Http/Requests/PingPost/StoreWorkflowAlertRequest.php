<?php

namespace App\Http\Requests\PingPost;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowAlertRequest extends FormRequest
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
      'alert_channel_id' => ['required', 'integer', 'exists:alert_channels,id'],
    ];
  }
}
