<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\LandingPage;

class Vertical extends Model
{
  protected $fillable = ['name', 'description', 'active', 'user_id', 'updated_user_id'];

  private function getAuthUserId()
  {
    return Auth::id();
  }
  //booting
  protected static function boot()
  {
    parent::boot();

    // Set user_id to auth user id when creating a vertical
    static::creating(function ($vertical) {
      $vertical->user_id = $vertical->getAuthUserId();
    });

    // Set updated_user_id to auth user id when updating a vertical
    static::updating(function ($vertical) {
      $vertical->updated_user_id = $vertical->getAuthUserId();
    });
  }

  // One vertical can have many landing pages
  public function landingPages()
  {
    return $this->hasMany(LandingPage::class);
  }
}
