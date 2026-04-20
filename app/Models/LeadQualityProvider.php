<?php

namespace App\Models;

use App\Enums\LeadQuality\LeadQualityProviderType;
use App\Enums\LeadQuality\ProviderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class LeadQualityProvider extends Model
{
  use HasFactory;

  protected $table = 'lead_quality_providers';

  protected $fillable = ['name', 'type', 'status', 'is_enabled', 'environment', 'credentials', 'settings', 'notes', 'created_by', 'updated_by'];

  protected $casts = [
    'type' => LeadQualityProviderType::class,
    'status' => ProviderStatus::class,
    'is_enabled' => 'boolean',
    'credentials' => 'encrypted:array',
    'settings' => 'array',
  ];

  protected $hidden = ['credentials'];

  protected static function boot(): void
  {
    parent::boot();

    static::creating(function (self $model): void {
      $model->created_by ??= Auth::id();
    });

    static::updating(function (self $model): void {
      $model->updated_by = Auth::id();
    });
  }

  public function validationRules(): HasMany
  {
    return $this->hasMany(LeadQualityValidationRule::class, 'provider_id');
  }

  public function creator(): BelongsTo
  {
    return $this->belongsTo(User::class, 'created_by');
  }

  public function updater(): BelongsTo
  {
    return $this->belongsTo(User::class, 'updated_by');
  }

  /**
   * External service request log entries attributable to this provider,
   * scoped by module + service_name + service_id (see ExternalServiceRequest).
   */
  public function requests(): HasMany
  {
    return $this->hasMany(ExternalServiceRequest::class, 'service_id')->where('module', 'lead_quality')->where('service_name', $this->type?->value);
  }

  public function isUsable(): bool
  {
    return $this->is_enabled && $this->status === ProviderStatus::ACTIVE;
  }

  /**
   * Returns credentials with obviously-secret keys masked.
   *
   * @return array<string, mixed>
   */
  public function maskedCredentials(): array
  {
    $credentials = $this->credentials ?? [];
    $sensitive = ['auth_token', 'api_key', 'secret', 'password', 'token'];

    foreach ($credentials as $key => $value) {
      if (in_array(strtolower((string) $key), $sensitive, true) && is_string($value) && $value !== '') {
        $credentials[$key] = str_repeat('*', max(4, strlen($value) - 4)) . substr($value, -4);
      }
    }

    return $credentials;
  }
}
