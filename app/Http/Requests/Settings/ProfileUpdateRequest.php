<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use App\Support\TimezoneOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
  protected function prepareForValidation(): void
  {
    if ($this->input('timezone') === '') {
      $this->merge(['timezone' => null]);
    }
  }

  /**
   * Get the validation rules that apply to the request.
   *
   * @return array<string, ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    return [
      'name' => ['required', 'string', 'max:255'],

      'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],

      'timezone' => ['nullable', 'string', Rule::in(TimezoneOptions::values())],
    ];
  }
}
