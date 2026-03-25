<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerEligibilityRule extends Model
{
    protected $fillable = [
        'integration_id',
        'field',
        'operator',
        'value',
        'sort_order',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Evaluate this rule against the given lead data.
     */
    public function evaluate(array $leadData): bool
    {
        $fieldValue = $leadData[$this->field] ?? null;
        $ruleValue = $this->value;

        return match ($this->operator) {
            'eq' => $fieldValue == $ruleValue,
            'neq' => $fieldValue != $ruleValue,
            'gt' => is_numeric($fieldValue) && $fieldValue > $ruleValue,
            'gte' => is_numeric($fieldValue) && $fieldValue >= $ruleValue,
            'lt' => is_numeric($fieldValue) && $fieldValue < $ruleValue,
            'lte' => is_numeric($fieldValue) && $fieldValue <= $ruleValue,
            'in' => in_array($fieldValue, (array) $ruleValue),
            'not_in' => ! in_array($fieldValue, (array) $ruleValue),
            default => false,
        };
    }
}
