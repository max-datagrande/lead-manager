<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Response configuration for post-type integration environments.
 *
 * Defines how to interpret the buyer's post response: how to determine
 * if the lead was accepted and where to extract the rejection reason
 * when the lead is declined.
 *
 * Used by both `ping-post` (post phase) and `post-only` integrations.
 *
 * @property int $id
 * @property int $integration_environment_id
 * @property string|null $accepted_path
 * @property string|null $accepted_value
 * @property string|null $rejected_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PostResponseConfig extends Model
{
  protected $guarded = [];

  /**
   * Get the integration environment that owns this config.
   */
  public function integrationEnvironment(): BelongsTo
  {
    return $this->belongsTo(IntegrationEnvironment::class);
  }
}
