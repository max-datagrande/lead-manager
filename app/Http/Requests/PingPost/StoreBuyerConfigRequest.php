<?php

namespace App\Http\Requests\PingPost;

use App\Enums\PriceSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBuyerConfigRequest extends FormRequest
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
    $isUpdate = $this->route('buyer') !== null;

    return [
      // Buyer-level fields
      'name' => ['required', 'string', 'max:255'],
      'integration_id' => $isUpdate ? ['exclude'] : ['required', 'exists:integrations,id'],
      'company_id' => ['nullable', 'exists:companies,id'],
      'is_active' => ['boolean'],

      // BuyerConfig fields
      'ping_timeout_ms' => ['nullable', 'integer', 'min:500', 'max:30000'],
      'post_timeout_ms' => ['nullable', 'integer', 'min:500', 'max:30000'],
      'price_source' => ['required', Rule::enum(PriceSource::class)],
      'fixed_price' => ['required_if:price_source,fixed', 'nullable', 'numeric', 'min:0'],
      'min_bid' => ['nullable', 'numeric', 'min:0'],
      'conditional_pricing_rules' => ['required_if:price_source,conditional', 'nullable', 'array'],
      'conditional_pricing_rules.*.conditions' => ['required', 'array', 'min:1'],
      'conditional_pricing_rules.*.conditions.*.field' => ['required', 'string'],
      'conditional_pricing_rules.*.conditions.*.op' => [
        'required',
        'string',
        Rule::in(['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in', 'is_empty', 'is_not_empty']),
      ],
      'conditional_pricing_rules.*.conditions.*.value' => ['required'],
      'conditional_pricing_rules.*.price' => ['required', 'numeric', 'min:0'],
      'postback_pending_days' => $this->input('price_source') === 'postback' ? ['required', 'integer', 'min:1', 'max:90'] : ['exclude'],
      'sell_on_zero_price' => ['boolean'],
    ];
  }
}
