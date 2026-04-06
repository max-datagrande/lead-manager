<?php

namespace App\Events;

use App\Models\LeadDispatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadSold
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public function __construct(public LeadDispatch $dispatch) {}
}
