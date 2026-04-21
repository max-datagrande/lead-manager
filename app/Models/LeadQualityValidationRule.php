<?php

namespace App\Models;

use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LeadQualityValidationRule extends Model
{
  use HasFactory;

  protected $table = 'lead_quality_validation_rules';

  protected $fillable = [
    'name',
    'slug',
    'validation_type',
    'provider_id',
    'status',
    'is_enabled',
    'description',
    'settings',
    'priority',
    'user_id',
    'updated_user_id',
  ];

  protected $casts = [
    'validation_type' => ValidationType::class,
    'status' => RuleStatus::class,
    'is_enabled' => 'boolean',
    'settings' => 'array',
    'priority' => 'integer',
  ];

  protected static function boot(): void
  {
    parent::boot();

    static::creating(function (self $model): void {
      if (empty($model->slug)) {
        $model->slug = static::uniqueSlug($model->name);
      }
      $model->user_id ??= Auth::id();
    });

    static::updating(function (self $model): void {
      $model->updated_user_id = Auth::id();
    });

    static::deleting(function (self $model): void {
      $model->updated_user_id = Auth::id();
    });
  }

  protected static function uniqueSlug(string $name): string
  {
    $base = Str::slug($name) ?: 'rule';
    $slug = $base;
    $i = 2;

    while (static::query()->where('slug', $slug)->exists()) {
      $slug = "{$base}-{$i}";
      $i++;
    }

    return $slug;
  }

  public function provider(): BelongsTo
  {
    return $this->belongsTo(LeadQualityProvider::class, 'provider_id');
  }

  public function buyers(): BelongsToMany
  {
    return $this->belongsToMany(Integration::class, 'buyer_validation_rule', 'validation_rule_id', 'integration_id')
      ->withPivot('is_enabled')
      ->withTimestamps();
  }

  public function logs(): HasMany
  {
    return $this->hasMany(LeadQualityValidationLog::class, 'validation_rule_id');
  }

  public function creator(): BelongsTo
  {
    return $this->belongsTo(User::class, 'user_id');
  }

  public function updater(): BelongsTo
  {
    return $this->belongsTo(User::class, 'updated_user_id');
  }

  public function scopeActive(Builder $query): Builder
  {
    return $query->where('status', RuleStatus::ACTIVE->value)->where('is_enabled', true);
  }

  public function scopeForIntegration(Builder $query, int $integrationId): Builder
  {
    return $query->whereHas('buyers', function (Builder $q) use ($integrationId): void {
      $q->where('integrations.id', $integrationId)->where('buyer_validation_rule.is_enabled', true);
    });
  }

  public function isAsync(): bool
  {
    return $this->validation_type?->isAsync() ?? false;
  }

  public function validityWindowMinutes(): int
  {
    return (int) ($this->settings['validity_window'] ?? 15);
  }

  public function maxAttempts(): int
  {
    return (int) ($this->settings['max_attempts'] ?? 3);
  }

  public function ttlSeconds(): int
  {
    return (int) ($this->settings['ttl'] ?? 600);
  }
}
