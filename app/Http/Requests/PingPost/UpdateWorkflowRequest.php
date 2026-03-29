<?php

namespace App\Http\Requests\PingPost;

use App\Enums\WorkflowStrategy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkflowRequest extends FormRequest
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
      'name' => ['sometimes', 'string', 'max:255'],
      'execution_mode' => ['sometimes', Rule::in(['sync', 'async'])],
      'strategy' => ['sometimes', Rule::enum(WorkflowStrategy::class)],
      'global_timeout_ms' => ['nullable', 'integer', 'min:500', 'max:60000'],
      'is_active' => ['boolean'],
      'cascade_on_post_rejection' => ['boolean'],
      'cascade_max_retries' => ['nullable', 'integer', 'min:1', 'max:10'],
      'advance_on_rejection' => ['boolean'],
      'advance_on_timeout' => ['boolean'],
      'advance_on_error' => ['boolean'],
      'buyers' => ['sometimes', 'array', 'min:1'],
      'buyers.*.buyer_id' => ['required_with:buyers', 'integer', 'exists:buyers,id'],
      'buyers.*.position' => ['required_with:buyers', 'integer', 'min:0'],
      'buyers.*.is_fallback' => ['boolean'],
      'buyers.*.buyer_group' => ['nullable', Rule::in(['primary', 'secondary'])],
      'buyers.*.is_active' => ['boolean'],
    ];
  }
}
