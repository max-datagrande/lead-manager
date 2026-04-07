<?php

namespace App\Http\Requests\Verticals;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'name' => 'required|string|max:100',
      'description' => 'nullable|string',
      'active' => 'boolean',
    ];
  }
}
