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
      'rules.*.operator' => ['required', 'string', Rule::in(['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in', 'is_empty', 'is_not_empty'])],
      'rules.*.value' => ['exclude_if:rules.*.operator,is_empty', 'exclude_if:rules.*.operator,is_not_empty', 'required'],
      'rules.*.sort_order' => ['nullable', 'integer', 'min:0'],
    ];
  }
}
