<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class LandingPage extends Model
{
  protected $fillable = [
    'name',
    'url',
    'is_external',
    'vertical_id',
    'company_id',
    'active',
    'user_id',
    'updated_user_id',
  ];
  private function getAuthUserId()
  {
    return Auth::id();
  }
  protected static function boot()
  {
    parent::boot();

    // Set user_id when creating a new record
    static::creating(function ($landingPage) {
      $landingPage->user_id = $landingPage->getAuthUserId();
    });

    // Set updated_user_id when updating a record
    static::updating(function ($landingPage) {
      $landingPage->updated_user_id = $landingPage->getAuthUserId();
    });
  }

  // Belongs to a vertical
  public function vertical()
  {
    return $this->belongsTo(Vertical::class);
  }

  // Belongs to a company (optional)
  public function company()
  {
    return $this->belongsTo(Company::class);
  }
}
