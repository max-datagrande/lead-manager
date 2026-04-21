<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPageVersion extends Model
{
  protected $fillable = ['landing_page_id', 'name', 'description', 'url', 'status'];

  public function landingPage()
  {
    return $this->belongsTo(LandingPage::class);
  }
}
