<?php

namespace App\Services\LeadQuality;

use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationLogStatus;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use Illuminate\Support\Collection;

/**
 * Safety net for the dispatch flow. The canonical path is: the landing page
 * drives `challenge/send` and `challenge/verify` via the public API before
 * calling dispatch. This checker protects against dispatches that bypass
 * that flow (direct curl, integration misconfiguration) by requiring that
 * every buyer with applicable rules has a recent `verified` validation log
 * tied to the same fingerprint.
 *
 * Mirrors the contract of `EligibilityCheckerService` so it slots naturally
 * as an extra gate inside `DispatchOrchestrator::getEligibleBuyers()`.
 */
class LeadQualityCheckerService
{
  /**
   * True if the buyer either has no quality rules or has at least one
   * verified, non-expired validation log for each of its enabled rules.
   *
   * @param  array<string, mixed>  $leadData  Unused today — kept for contract parity with EligibilityCheckerService.
   */
  public function isEligibleForQuality(Integration $buyer, Lead $lead, array $leadData = []): bool
  {
    $rules = $this->applicableRules($buyer);

    if ($rules->isEmpty()) {
      return true;
    }

    foreach ($rules as $rule) {
      if (!$this->hasVerifiedLogWithinWindow($rule, $buyer, $lead)) {
        return false;
      }
    }

    return true;
  }

  /**
   * Short, human-friendly reason for the first rule that fails to find a
   * verified log. Returns null when everything passes.
   */
  public function getSkipReason(Integration $buyer, Lead $lead, array $leadData = []): ?string
  {
    $rules = $this->applicableRules($buyer);

    foreach ($rules as $rule) {
      if (!$this->hasVerifiedLogWithinWindow($rule, $buyer, $lead)) {
        $latest = $this->latestLogFor($rule, $buyer, $lead);

        if (!$latest) {
          return "Rule '{$rule->name}': no validation attempt on record";
        }

        if ($latest->status === ValidationLogStatus::EXPIRED || $latest->isExpired()) {
          return "Rule '{$rule->name}': last attempt expired";
        }

        if ($latest->status === ValidationLogStatus::FAILED) {
          return "Rule '{$rule->name}': last attempt failed";
        }

        if (in_array($latest->status, [ValidationLogStatus::PENDING, ValidationLogStatus::SENT], true)) {
          return "Rule '{$rule->name}': challenge pending, not verified yet";
        }

        return "Rule '{$rule->name}': no verified attempt within validity window";
      }
    }

    return null;
  }

  /**
   * @return Collection<int, LeadQualityValidationRule>
   */
  private function applicableRules(Integration $buyer): Collection
  {
    return $buyer
      ->validationRules()
      ->wherePivot('is_enabled', true)
      ->where('lead_quality_validation_rules.status', RuleStatus::ACTIVE->value)
      ->where('lead_quality_validation_rules.is_enabled', true)
      ->get();
  }

  private function hasVerifiedLogWithinWindow(LeadQualityValidationRule $rule, Integration $buyer, Lead $lead): bool
  {
    $windowMinutes = $rule->validityWindowMinutes();

    return LeadQualityValidationLog::query()
      ->where('validation_rule_id', $rule->id)
      ->where('integration_id', $buyer->id)
      ->where('status', ValidationLogStatus::VERIFIED->value)
      ->where(function ($q) use ($lead): void {
        $q->where('lead_id', $lead->id);
        if ($lead->fingerprint) {
          $q->orWhere('fingerprint', $lead->fingerprint);
        }
      })
      ->where('resolved_at', '>=', now()->subMinutes($windowMinutes))
      ->exists();
  }

  private function latestLogFor(LeadQualityValidationRule $rule, Integration $buyer, Lead $lead): ?LeadQualityValidationLog
  {
    return LeadQualityValidationLog::query()
      ->where('validation_rule_id', $rule->id)
      ->where('integration_id', $buyer->id)
      ->where(function ($q) use ($lead): void {
        $q->where('lead_id', $lead->id);
        if ($lead->fingerprint) {
          $q->orWhere('fingerprint', $lead->fingerprint);
        }
      })
      ->latest('id')
      ->first();
  }
}
