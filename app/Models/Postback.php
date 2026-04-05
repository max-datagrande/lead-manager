<?php

namespace App\Models;

use App\Enums\FireMode;
use App\Enums\PostbackType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Postback extends Model
{
  use HasFactory;

  protected $table = 'postbacks';

  protected $fillable = [
    'uuid',
    'name',
    'type',
    'platform_id',
    'base_url',
    'param_mappings',
    'result_url',
    'fire_mode',
    'is_active',
    'is_public',
    'last_fired_at',
    'user_id',
    'updated_user_id',
  ];

  protected $casts = [
    'param_mappings' => 'array',
    'type' => PostbackType::class,
    'fire_mode' => FireMode::class,
    'is_active' => 'boolean',
    'is_public' => 'boolean',
    'last_fired_at' => 'datetime',
  ];

  protected $appends = ['generated_url'];

  protected static function boot(): void
  {
    parent::boot();

    static::creating(function (self $model): void {
      $model->uuid = (string) Str::uuid();
      $model->user_id ??= Auth::id();
    });

    static::updating(function (self $model): void {
      $model->updated_user_id = Auth::id();
    });

    static::deleting(function (self $model): void {
      $model->updated_user_id = Auth::id();
    });
  }

  /**
   * Genera la URL pública para el partner.
   * Usa nuestros tokens internos como param keys y tokens de la platform como placeholders.
   * Ejemplo: ourdomain.com/v1/postback/fire/{uuid}?click_id={Callid}&revenue={Cost}
   */
  public function getGeneratedUrlAttribute(): string
  {
    $domain = $this->is_public ? config('app.api_url') : config('app.url');

    if ($this->type === PostbackType::INTERNAL) {
      $base = rtrim($domain, '/') . '/v1/postback/fire/' . $this->uuid . '/{fingerprint}';

      if (empty($this->param_mappings)) {
        return $base;
      }

      $params = [];

      foreach ($this->param_mappings as $destParam => $tokenName) {
        if (str_starts_with($tokenName, 'traffic.')) {
          continue;
        }
        $params[$tokenName] = '{' . $tokenName . '}';
      }

      if (empty($params)) {
        return $base;
      }

      $query = str_replace(['%7B', '%7D'], ['{', '}'], http_build_query($params));

      return $base . '?' . $query;
    }

    $base = rtrim($domain, '/') . '/v1/postback/fire/' . $this->uuid;

    if (empty($this->param_mappings)) {
      return $base;
    }

    $platform = $this->relationLoaded('platform') ? $this->platform : null;
    $inverseMapping = $platform ? $platform->getInverseMapping() : [];

    $params = [];

    foreach (array_unique(array_values($this->param_mappings)) as $internalToken) {
      $externalToken = $inverseMapping[$internalToken] ?? $internalToken;
      $params[$internalToken] = '{' . $externalToken . '}';
    }

    if (empty($params)) {
      return $base;
    }

    $query = str_replace(['%7B', '%7D'], ['{', '}'], http_build_query($params));

    return $base . '?' . $query;
  }

  /**
   * Construye la URL outbound final reemplazando tokens con valores reales.
   *
   * @param  array<string, string>  $internalValues  Valores con keys de tokens internos
   */
  public function buildOutboundUrl(array $internalValues): string
  {
    $parsed = parse_url($this->base_url);
    $params = [];

    foreach ($this->param_mappings as $destParam => $internalToken) {
      $params[$destParam] = $internalValues[$internalToken] ?? '';
    }

    $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . ($parsed['path'] ?? '');

    return $base . '?' . http_build_query($params);
  }

  /**
   * @param  Builder<self>  $query
   * @return Builder<self>
   */
  public function scopeActive(Builder $query): Builder
  {
    return $query->where('is_active', true);
  }

  /**
   * @param  Builder<self>  $query
   * @return Builder<self>
   */
  public function scopeInternal(Builder $query): Builder
  {
    return $query->where('type', PostbackType::INTERNAL);
  }

  /**
   * @param  Builder<self>  $query
   * @return Builder<self>
   */
  public function scopeExternal(Builder $query): Builder
  {
    return $query->where('type', PostbackType::EXTERNAL);
  }

  public function isInternal(): bool
  {
    return $this->type === PostbackType::INTERNAL;
  }

  public function executions(): HasMany
  {
    return $this->hasMany(PostbackExecution::class);
  }

  public function platform(): BelongsTo
  {
    return $this->belongsTo(Platform::class);
  }

  public function creator(): BelongsTo
  {
    return $this->belongsTo(User::class, 'user_id');
  }

  public function updater(): BelongsTo
  {
    return $this->belongsTo(User::class, 'updated_user_id');
  }
}
