<?php

namespace App\Services\LeadQuality;

use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use Illuminate\Support\Collection;

/**
 * Immutable per-dispatch snapshot of rule applicability + verified logs + latest
 * logs, built once via `LeadQualityCheckerService::prefetchForBuyers()` and
 * reused across all buyers in the dispatch. Keeps the safety net's runtime
 * cost bounded to a constant number of queries regardless of buyer count.
 */
class LeadQualityCheckerSnapshot
{
  /**
   * @param  array<int, array<int, LeadQualityValidationRule>>  $rulesByBuyer  integration_id => [rule_id => rule]
   * @param  array<int, true>  $verifiedRuleIds  rule_ids with a verified, in-window log
   * @param  array<int, LeadQualityValidationLog>  $latestByRule  rule_id => latest log (for missing rules only)
   */
  public function __construct(private readonly array $rulesByBuyer, private readonly array $verifiedRuleIds, private readonly array $latestByRule) {}

  public static function empty(): self
  {
    return new self([], [], []);
  }

  /**
   * @return Collection<int, LeadQualityValidationRule>
   */
  public function rulesFor(int $buyerId): Collection
  {
    return collect($this->rulesByBuyer[$buyerId] ?? []);
  }

  public function hasVerified(int $ruleId): bool
  {
    return isset($this->verifiedRuleIds[$ruleId]);
  }

  public function latestLogFor(int $ruleId): ?LeadQualityValidationLog
  {
    return $this->latestByRule[$ruleId] ?? null;
  }
}
