<?php

namespace App\Services\PingPost;

use App\Models\Integration;

class EligibilityCheckerService
{
    /**
     * Check if an integration is eligible for the given lead data
     * by evaluating all its eligibility rules (AND logic).
     */
    public function isEligible(Integration $integration, array $leadData): bool
    {
        $rules = $integration->eligibilityRules;

        if ($rules->isEmpty()) {
            return true;
        }

        foreach ($rules as $rule) {
            if (! $rule->evaluate($leadData)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return the first failing rule description, or null if all pass.
     */
    public function getSkipReason(Integration $integration, array $leadData): ?string
    {
        foreach ($integration->eligibilityRules as $rule) {
            if (! $rule->evaluate($leadData)) {
                return "Rule failed: field={$rule->field} operator={$rule->operator}";
            }
        }

        return null;
    }
}
