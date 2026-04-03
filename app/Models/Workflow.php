<?php

namespace App\Models;

use App\Enums\WorkflowStrategy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Workflow extends Model
{
  use HasFactory;

  protected $fillable = [
    'name',
    'execution_mode',
    'strategy',
    'global_timeout_ms',
    'is_active',
    'user_id',
    'cascade_on_post_rejection',
    'cascade_max_retries',
    'advance_on_rejection',
    'advance_on_timeout',
    'advance_on_error',
  ];

  protected $casts = [
    'strategy' => WorkflowStrategy::class,
    'is_active' => 'boolean',
    'cascade_on_post_rejection' => 'boolean',
    'advance_on_rejection' => 'boolean',
    'advance_on_timeout' => 'boolean',
    'advance_on_error' => 'boolean',
  ];

  protected static function booted(): void
  {
    static::creating(function (Workflow $workflow): void {
      $workflow->user_id ??= Auth::id();
    });
  }

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  public function workflowBuyers(): HasMany
  {
    return $this->hasMany(WorkflowBuyer::class)->orderBy('position');
  }

  public function integrations(): BelongsToMany
  {
    return $this->belongsToMany(Integration::class, 'workflow_buyers')
      ->using(WorkflowBuyer::class)
      ->withPivot(['position', 'is_fallback', 'buyer_group', 'is_active'])
      ->withTimestamps();
  }

  public function dispatches(): HasMany
  {
    return $this->hasMany(LeadDispatch::class);
  }

  public function scopeActive(Builder $query): Builder
  {
    return $query->where('is_active', true);
  }
}
