<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Platform extends Model
{
  use HasFactory;

  protected $fillable = [
    'name',
    'company_id',
    'token_mappings',
    'user_id',
    'updated_user_id',
  ];

  protected $casts = [
    'token_mappings' => 'array',
  ];

  protected static function boot(): void
  {
    parent::boot();

    static::creating(function (self $model): void {
      $model->user_id ??= Auth::id();
    });
    static::deleting(function (self $model): void {
      $model->updated_user_id = Auth::id();
    });
    static::updating(function (self $model): void {
      $model->updated_user_id = Auth::id();
    });
  }

  /**
   * @return array<int, string>
   */
  public function getInternalTokensAttribute(): array
  {
    return array_unique(array_values($this->token_mappings ?? []));
  }

  /**
   * @return array<string, string> internal → external
   */
  public function getInverseMapping(): array
  {
    return array_flip($this->token_mappings ?? []);
  }

  public function company(): BelongsTo
  {
    return $this->belongsTo(Company::class);
  }

  public function creator(): BelongsTo
  {
    return $this->belongsTo(User::class, 'user_id');
  }

  public function updater(): BelongsTo
  {
    return $this->belongsTo(User::class, 'updated_user_id');
  }

  public function postbacks(): HasMany
  {
    return $this->hasMany(Postback::class);
  }
}
