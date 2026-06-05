<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a lead dispatch was redirected to a different workflow than the one
 * the landing intended, via the Catalyst SDK `?workflow_id` URL override. Carries
 * scalars only so the listener stays queue-safe and resolves names / traffic log
 * details on its own.
 */
class LeadWorkflowOverridden
{
  use Dispatchable, SerializesModels;

  /**
   * @param  string  $idIntended  Workflow id the landing intended to dispatch to.
   * @param  string  $idEffective  Workflow id the dispatch was redirected to (URL param).
   * @param  string  $fingerprint  Visitor fingerprint of the dispatched lead.
   * @param  int  $leadId  Id of the dispatched lead (traceability only).
   */
  public function __construct(public string $idIntended, public string $idEffective, public string $fingerprint, public int $leadId) {}
}
