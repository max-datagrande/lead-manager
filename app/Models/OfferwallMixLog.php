<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferwallMixLog extends Model
{
    protected $guarded = [];

    public function offerwallMix()
    {
        return $this->belongsTo(OfferwallMix::class);
    }

    public function integrationCallLogs()
    {
        return $this->morphMany(IntegrationCallLog::class, 'loggable');
    }
}
