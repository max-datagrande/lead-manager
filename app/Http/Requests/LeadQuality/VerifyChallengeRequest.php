<?php

namespace App\Http\Requests\LeadQuality;

use Illuminate\Foundation\Http\FormRequest;

class VerifyChallengeRequest extends FormRequest
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
      'challenge_token' => ['required', 'string'],
      'code' => ['required', 'string', 'min:4', 'max:12'],
      'to' => ['nullable', 'string', 'max:120'],
    ];
  }
}
