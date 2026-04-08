<?php

namespace App\Http\Requests;

use App\Services\Alerts\AlertChannelResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlertChannelRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    $resolver = app(AlertChannelResolver::class);

    return [
      'name' => ['required', 'string', 'max:255'],
      'type' => ['required', 'string', Rule::in($resolver->registeredTypeNames())],
      'webhook_url' => ['nullable', 'url', 'max:2048'],
      'active' => ['sometimes', 'boolean'],
    ];
  }
}
