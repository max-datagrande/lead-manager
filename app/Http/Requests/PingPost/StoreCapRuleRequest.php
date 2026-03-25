<?php

namespace App\Http\Requests\PingPost;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCapRuleRequest extends FormRequest
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
            'caps' => ['required', 'array'],
            'caps.*.period' => ['required', 'string', Rule::in(['day', 'week', 'month', 'year'])],
            'caps.*.max_leads' => ['nullable', 'integer', 'min:1'],
            'caps.*.max_revenue' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            foreach ($this->input('caps', []) as $i => $cap) {
                if (empty($cap['max_leads']) && empty($cap['max_revenue'])) {
                    $v->errors()->add("caps.{$i}", 'Each cap rule must have at least max_leads or max_revenue.');
                }
            }
        });
    }
}
