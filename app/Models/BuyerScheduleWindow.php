<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerScheduleWindow extends Model
{
  protected $fillable = ['buyer_id', 'days_of_week', 'start_time', 'end_time', 'sort_order'];

  protected $casts = [
    'days_of_week' => 'array',
  ];

  public function buyer(): BelongsTo
  {
    return $this->belongsTo(Buyer::class);
  }
}
