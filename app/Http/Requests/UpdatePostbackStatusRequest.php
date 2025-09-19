<?php

namespace App\Http\Requests;

use App\Models\Postback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostbackStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                Postback::STATUS_PENDING,
                Postback::STATUS_PROCESSED,
                Postback::STATUS_FAILED,
            ])],
            'message' => ['nullable', 'string', 'max:2048'],
        ];
    }
}