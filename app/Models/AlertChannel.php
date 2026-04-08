<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AlertChannel extends Model
{
  use HasFactory;

  protected $fillable = ['name', 'type', 'webhook_url', 'active'];

  protected $casts = [
    'active' => 'boolean',
  ];

  protected static function boot(): void
  {
    parent::boot();

    static::creating(function (self $model): void {
      $model->user_id ??= Auth::id();
    });
    static::updating(function (self $model): void {
      $model->updated_user_id = Auth::id();
    });
    static::deleting(function (self $model): void {
      $model->updated_user_id = Auth::id();
    });
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
