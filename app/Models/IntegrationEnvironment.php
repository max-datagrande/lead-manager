<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Represents a specific environment configuration (development/production)
 * for an integration. Each environment stores the HTTP connection details
 * (URL, method, headers, body) and links to a typed response config table
 * based on its `env_type`.
 *
 * @property int $id
 * @property int $integration_id
 * @property string $environment
 * @property string $env_type
 * @property string $method
 * @property string $url
 * @property string|null $request_body
 * @property string|null $request_headers
 * @property string|null $content_type
 * @property string|null $authentication_type
 * @property-read OfferwallResponseConfig|PingResponseConfig|PostResponseConfig|null $response_config
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class IntegrationEnvironment extends Model
{
  use HasFactory;

  public const ENV_TYPE_OFFERWALL = 'offerwall';

  public const ENV_TYPE_PING = 'ping';

  public const ENV_TYPE_POST = 'post';

  protected $fillable = [
    'integration_id',
    'environment',
    'env_type',
    'method',
    'url',
    'request_body',
    'request_headers',
    'content_type',
    'authentication_type',
  ];

  protected $with = [
    'offerwallResponseConfig',
    'pingResponseConfig',
    'postResponseConfig',
  ];

  protected $appends = [
    'response_config',
  ];

  /**
   * Resolve the typed response config for this environment.
   *
   * Returns the correct config model based on `env_type`:
   * - offerwall → OfferwallResponseConfig
   * - ping      → PingResponseConfig
   * - post      → PostResponseConfig
   */
  protected function responseConfig(): Attribute
  {
    return Attribute::get(fn () => match ($this->env_type) {
      self::ENV_TYPE_OFFERWALL => $this->offerwallResponseConfig,
      self::ENV_TYPE_PING => $this->pingResponseConfig,
      self::ENV_TYPE_POST => $this->postResponseConfig,
      default => null,
    });
  }

  /**
   * Get the integration that owns the environment.
   */
  public function integration(): BelongsTo
  {
    return $this->belongsTo(Integration::class);
  }

  /**
   * Get the offerwall response config (only for env_type = offerwall).
   */
  public function offerwallResponseConfig(): HasOne
  {
    return $this->hasOne(OfferwallResponseConfig::class);
  }

  /**
   * Get the ping response config (only for env_type = ping).
   */
  public function pingResponseConfig(): HasOne
  {
    return $this->hasOne(PingResponseConfig::class);
  }

  /**
   * Get the post response config (only for env_type = post).
   */
  public function postResponseConfig(): HasOne
  {
    return $this->hasOne(PostResponseConfig::class);
  }
}
