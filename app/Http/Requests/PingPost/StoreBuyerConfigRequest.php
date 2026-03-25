<?php

namespace App\Http\Requests\PingPost;

use App\Enums\PricingType;
use App\Models\Integration;
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
        $isPingPost = $this->resolveIntegrationType() === 'ping-post';
        $isUpdate = $this->route('buyer') !== null;

        return [
            // Buyer-level fields
            'name' => ['required', 'string', 'max:255'],
            'integration_id' => $isUpdate
                ? ['prohibited']
                : ['required', 'exists:integrations,id', Rule::unique('buyers', 'integration_id')],
            'company_id' => ['nullable', 'exists:companies,id'],
            'is_active' => ['boolean'],

            // BuyerConfig fields
            'ping_timeout_ms' => ['nullable', 'integer', 'min:500', 'max:30000'],
            'post_timeout_ms' => ['nullable', 'integer', 'min:500', 'max:30000'],
            'ping_response_config' => [$isPingPost ? 'required' : 'nullable', 'array'],
            'ping_response_config.bid_price_path' => ['nullable', 'string'],
            'ping_response_config.accepted_path' => ['nullable', 'string'],
            'ping_response_config.accepted_value' => ['nullable', 'string'],
            'post_response_config' => ['nullable', 'array'],
            'post_response_config.accepted_path' => ['nullable', 'string'],
            'post_response_config.accepted_value' => ['nullable', 'string'],
            'post_response_config.rejected_path' => ['nullable', 'string'],
            'pricing_type' => ['required', Rule::enum(PricingType::class)],
            'fixed_price' => ['required_if:pricing_type,fixed', 'nullable', 'numeric', 'min:0'],
            'min_bid' => ['required_if:pricing_type,min_bid', 'nullable', 'numeric', 'min:0'],
            'conditional_pricing_rules' => ['required_if:pricing_type,conditional', 'nullable', 'array'],
            'conditional_pricing_rules.*.conditions' => ['required', 'array', 'min:1'],
            'conditional_pricing_rules.*.conditions.*.field' => ['required', 'string'],
            'conditional_pricing_rules.*.conditions.*.op' => ['required', 'string', Rule::in(['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in'])],
            'conditional_pricing_rules.*.conditions.*.value' => ['required'],
            'conditional_pricing_rules.*.price' => ['required', 'numeric', 'min:0'],
            'postback_pending_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ];
    }

    private function resolveIntegrationType(): ?string
    {
        /** @var \App\Models\Buyer|null $buyer */
        $buyer = $this->route('buyer');

        if ($buyer) {
            return $buyer->integration?->type;
        }

        $integrationId = $this->input('integration_id');

        return $integrationId ? Integration::find($integrationId)?->type : null;
    }
}
