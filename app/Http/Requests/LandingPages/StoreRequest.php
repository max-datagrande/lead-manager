<?php

namespace App\Http\Requests\LandingPages;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'name'        => 'required|string|max:150',
      'url'         => [
        'required',
        'string',
        'max:255',
        Rule::unique('landing_pages', 'url'),
      ],
      'is_external' => 'boolean',
      'vertical_id' => 'required|exists:verticals,id',
      'company_id' => ['nullable', 'exists:companies,id', Rule::requiredIf(fn() => $this->boolean('is_external'))],
      'active'      => 'boolean',
    ];
  }
}
