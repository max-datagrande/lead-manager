<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class OfferwallMix extends Model
{
  use HasFactory;

  protected $fillable = [
    'name',
    'description',
    'user_id',
    'is_active',
  ];

  protected $casts = [
    'is_active' => 'boolean',
  ];

  protected static function booted(): void
  {
    static::creating(function (OfferwallMix $mix) {
      $mix->user_id         = Auth::id();
      /* $mix->updated_user_id = Auth::id(); */
    });

    static::updating(function (OfferwallMix $mix) {
      /* $mix->updated_user_id = Auth::id(); */
    });
  }

  /**
   * Get the user that owns the offerwall mix.
   */
  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  /**
   * The integrations that belong to the offerwall mix.
   */
  public function integrations(): BelongsToMany
  {
    return $this->belongsToMany(Integration::class, 'offerwall_mix_integrations')
      ->withTimestamps();
  }
}
