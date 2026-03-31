<?php

namespace App\Http\Requests\PingPost;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEligibilityRuleRequest extends FormRequest
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
            'rules' => ['required', 'array'],
            'rules.*.field' => ['required', 'string', 'max:100'],
            'rules.*.operator' => ['required', 'string', Rule::in(['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in'])],
            'rules.*.value' => ['required'],
            'rules.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
