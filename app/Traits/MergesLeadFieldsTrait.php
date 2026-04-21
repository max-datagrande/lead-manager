<?php

namespace App\Traits;

use App\Models\Lead;
use App\Services\LeadService;

/**
 * Applies a merge-update of field responses onto a `Lead` in one call, as a
 * side-effect step shared by landing-facing endpoints (registerLead, shareLead,
 * requestChallenge, dispatch build-in-one, etc.).
 *
 * The repetition across controllers is just a guard + a delegation to
 * `LeadService::processLeadFields`; centralizing it here keeps the "merge
 * contract" (null/empty short-circuit, atomic abort on validation failure,
 * consistent return shape) in one place so callers only worry about when to
 * invoke it.
 */
trait MergesLeadFieldsTrait
{
  /**
   * Merge `$fields` onto the lead. Returns the processing summary from
   * LeadService, or null when there is nothing to do.
   *
   * Errors (including validation failures inside LeadService) are propagated
   * to the caller so the surrounding request aborts atomically — partial
   * writes would make the "fields applied + challenge issued" contract
   * unpredictable for the landing.
   *
   * @param  array<string, mixed>|null  $fields
   * @return array{created_count: int, updated_count: int, created_fields: array, updated_fields: array}|null
   */
  protected function mergeLeadFields(Lead $lead, ?array $fields): ?array
  {
    if (empty($fields)) {
      return null;
    }

    return app(LeadService::class)->processLeadFields($lead, $fields);
  }
}
