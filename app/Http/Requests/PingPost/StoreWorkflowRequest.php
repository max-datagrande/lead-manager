<?php

namespace App\Http\Requests\PingPost;

use App\Enums\WorkflowStrategy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowRequest extends FormRequest
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
      'name' => ['required', 'string', 'max:255'],
      'execution_mode' => ['required', Rule::in(['sync', 'async'])],
      'strategy' => ['required', Rule::enum(WorkflowStrategy::class)],
      'global_timeout_ms' => ['nullable', 'integer', 'min:500', 'max:60000'],
      'is_active' => ['boolean'],
      'cascade_on_post_rejection' => ['boolean'],
      'cascade_max_retries' => ['nullable', 'integer', 'min:1', 'max:10'],
      'advance_on_rejection' => ['boolean'],
      'advance_on_timeout' => ['boolean'],
      'advance_on_error' => ['boolean'],
      'buyers' => ['required', 'array', 'min:1'],
      'buyers.*.buyer_id' => ['required', 'integer', 'exists:buyers,id'],
      'buyers.*.position' => ['required', 'integer', 'min:0'],
      'buyers.*.is_fallback' => ['boolean'],
      'buyers.*.buyer_group' => ['nullable', Rule::in(['primary', 'secondary'])],
      'buyers.*.is_active' => ['boolean'],
    ];
  }
}
