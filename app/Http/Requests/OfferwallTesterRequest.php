<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OfferwallTesterRequest extends FormRequest
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
            'field_values' => 'required|array',
            'field_values.*' => 'nullable|string|max:500',
        ];
    }
}
