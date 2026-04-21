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
 *
 * Supports two usage patterns:
 *
 *   1. Per-buyer (simple): `$checker->isEligibleForQuality($buyer, $lead)`.
 *      One query per buyer per rule. Fine for unit tests and one-offs.
 *
 *   2. Prefetched (batch): `$snapshot = $checker->prefetchForBuyers($buyers, $lead);`
 *      then `$checker->isEligibleForQuality($buyer, $lead, [], $snapshot);`.
 *      One query for all buyers and rules combined. Used by the orchestrator
 *      to keep the per-dispatch cost bounded even with many buyers.
 */
class LeadQualityCheckerService
{
  /**
   * True when the buyer has no applicable rules or every applicable rule has a
   * verified, non-expired log for this lead/fingerprint. Optionally consults a
   * pre-computed snapshot (built via `prefetchForBuyers`) to avoid per-buyer
   * queries.
   *
   * @param  array<string, mixed>  $leadData  Unused today — kept for contract parity with EligibilityCheckerService.
   */
  public function isEligibleForQuality(Integration $buyer, Lead $lead, array $leadData = [], ?LeadQualityCheckerSnapshot $snapshot = null): bool
  {
    $rules = $snapshot?->rulesFor($buyer->id) ?? $this->applicableRules($buyer);

    if ($rules->isEmpty()) {
      return true;
    }

    foreach ($rules as $rule) {
      if (!$this->ruleIsVerified($rule, $lead, $snapshot)) {
        return false;
      }
    }

    return true;
  }

  /**
   * Short, human-friendly reason for the first rule that fails to find a
   * verified log. Returns null when everything passes.
   */
  public function getSkipReason(Integration $buyer, Lead $lead, array $leadData = [], ?LeadQualityCheckerSnapshot $snapshot = null): ?string
  {
    $rules = $snapshot?->rulesFor($buyer->id) ?? $this->applicableRules($buyer);

    foreach ($rules as $rule) {
      if ($this->ruleIsVerified($rule, $lead, $snapshot)) {
        continue;
      }

      $latest = $snapshot?->latestLogFor($rule->id) ?? $this->latestLogFor($rule, $lead);

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

    return null;
  }

  /**
   * Build a prefetched snapshot of rules + verified logs + latest-per-rule for
   * a set of buyers. Runs three queries total regardless of buyer count.
   *
   * @param  iterable<Integration>  $buyers
   */
  public function prefetchForBuyers(iterable $buyers, Lead $lead): LeadQualityCheckerSnapshot
  {
    $buyerIds = collect($buyers)->pluck('id')->filter()->unique()->values();

    if ($buyerIds->isEmpty()) {
      return LeadQualityCheckerSnapshot::empty();
    }

    // Query 1: rules attached to any of the buyers, with pivot enabled and rule active.
    $rulesByBuyer = [];
    $ruleIndex = [];
    LeadQualityValidationRule::query()
      ->where('lead_quality_validation_rules.status', RuleStatus::ACTIVE->value)
      ->where('lead_quality_validation_rules.is_enabled', true)
      ->whereHas('buyers', fn($q) => $q->whereIn('integrations.id', $buyerIds)->where('buyer_validation_rule.is_enabled', true))
      ->with([
        'buyers' => fn($q) => $q->whereIn('integrations.id', $buyerIds)->where('buyer_validation_rule.is_enabled', true),
      ])
      ->get()
      ->each(function (LeadQualityValidationRule $rule) use (&$rulesByBuyer, &$ruleIndex): void {
        $ruleIndex[$rule->id] = $rule;
        foreach ($rule->buyers as $buyer) {
          $rulesByBuyer[$buyer->id] ??= [];
          $rulesByBuyer[$buyer->id][$rule->id] = $rule;
        }
      });

    if (empty($ruleIndex)) {
      return LeadQualityCheckerSnapshot::empty();
    }

    $ruleIds = array_keys($ruleIndex);
    $now = now();

    // Query 2: verified logs per rule within each rule's validity window.
    // We fetch everything verified for these rules/fingerprint/lead and filter in PHP
    // using the per-rule window — avoids a correlated subquery and plays well with SQLite.
    $verifiedLogs = LeadQualityValidationLog::query()
      ->whereIn('validation_rule_id', $ruleIds)
      ->where('status', ValidationLogStatus::VERIFIED->value)
      ->where(function ($q) use ($lead): void {
        $q->where('lead_id', $lead->id);
        if ($lead->fingerprint) {
          $q->orWhere('fingerprint', $lead->fingerprint);
        }
      })
      ->get();

    $verifiedRuleIds = [];
    foreach ($verifiedLogs as $log) {
      $rule = $ruleIndex[$log->validation_rule_id] ?? null;
      if (!$rule) {
        continue;
      }
      $window = $rule->validityWindowMinutes();
      if ($log->resolved_at && $log->resolved_at->greaterThanOrEqualTo($now->copy()->subMinutes($window))) {
        $verifiedRuleIds[$log->validation_rule_id] = true;
      }
    }

    // Query 3: latest log per rule for the failure-reason breakdown (only when verification is missing).
    $missingRuleIds = array_diff($ruleIds, array_keys($verifiedRuleIds));
    $latestByRule = [];
    if (!empty($missingRuleIds)) {
      $latestByRule = LeadQualityValidationLog::query()
        ->whereIn('validation_rule_id', $missingRuleIds)
        ->where(function ($q) use ($lead): void {
          $q->where('lead_id', $lead->id);
          if ($lead->fingerprint) {
            $q->orWhere('fingerprint', $lead->fingerprint);
          }
        })
        ->orderByDesc('id')
        ->get()
        ->groupBy('validation_rule_id')
        ->map(fn($group) => $group->first())
        ->all();
    }

    return new LeadQualityCheckerSnapshot($rulesByBuyer, $verifiedRuleIds, $latestByRule);
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

  private function ruleIsVerified(LeadQualityValidationRule $rule, Lead $lead, ?LeadQualityCheckerSnapshot $snapshot): bool
  {
    if ($snapshot) {
      return $snapshot->hasVerified($rule->id);
    }

    return $this->hasVerifiedLogWithinWindow($rule, $lead);
  }

  private function hasVerifiedLogWithinWindow(LeadQualityValidationRule $rule, Lead $lead): bool
  {
    $windowMinutes = $rule->validityWindowMinutes();

    // A validation log certifies that a given fingerprint passed a given rule.
    // Rules can apply to many buyers, so the log is NOT scoped by integration_id;
    // every buyer that attaches the rule accepts the same certificate.
    return LeadQualityValidationLog::query()
      ->where('validation_rule_id', $rule->id)
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

  private function latestLogFor(LeadQualityValidationRule $rule, Lead $lead): ?LeadQualityValidationLog
  {
    return LeadQualityValidationLog::query()
      ->where('validation_rule_id', $rule->id)
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
