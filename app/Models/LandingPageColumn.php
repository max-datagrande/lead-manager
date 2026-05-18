<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPageColumn extends Model
{
  public const SOURCE_FIELD = 'field';
  public const SOURCE_TRAFFIC = 'traffic';

  protected $fillable = ['landing_page_id', 'source', 'reference'];

  public function landingPage(): BelongsTo
  {
    return $this->belongsTo(LandingPage::class);
  }

  public function field(): BelongsTo
  {
    return $this->belongsTo(Field::class, 'reference');
  }
}
