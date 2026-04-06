<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Integration extends Model
{
  use HasFactory;

  protected $fillable = ['company_id', 'name', 'type', 'is_active', 'payload_transformer', 'use_custom_transformer', 'user_id', 'updated_user_id'];

  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
    'use_custom_transformer' => 'boolean',
  ];

  /**
   * Get the environments for the integration.
   */
  public function environments()
  {
    return $this->hasMany(IntegrationEnvironment::class);
  }

  /**
   * Get the company that owns the integration.
   */
  public function company()
  {
    return $this->belongsTo(Company::class);
  }

  /**
   * Get the token mappings for the integration (new {$field_id} token system).
   */
  public function tokenMappings(): HasMany
  {
    return $this->hasMany(IntegrationFieldMapping::class);
  }

  /**
   * Get the buyer record wrapping this integration.
   */
  public function buyer(): HasOne
  {
    return $this->hasOne(Buyer::class);
  }

  /**
   * Get the buyer config for this integration.
   */
  public function buyerConfig(): HasOne
  {
    return $this->hasOne(BuyerConfig::class);
  }

  /**
   * Get the eligibility rules for this integration.
   */
  public function eligibilityRules(): HasMany
  {
    return $this->hasMany(BuyerEligibilityRule::class)->orderBy('sort_order');
  }

  /**
   * Get the cap rules for this integration.
   */
  public function capRules(): HasMany
  {
    return $this->hasMany(BuyerCapRule::class);
  }

  /**
   * Scope to filter active offerwall integrations.
   */
  public function scopeActiveOfferwalls($query)
  {
    return $query->where('type', 'offerwall')->where('is_active', true);
  }

  /**
   * Scope to filter active ping-post and post-only integrations.
   */
  public function scopeActivePingPost(Builder $query): Builder
  {
    return $query->whereIn('type', ['ping-post', 'post-only'])->where('is_active', true);
  }

  /**
   * Truncate the table.
   */
  public static function truncate()
  {
    DB::statement('TRUNCATE TABLE integrations RESTART IDENTITY CASCADE');
  }
}
