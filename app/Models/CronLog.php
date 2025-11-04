<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronLog extends Model
{
  protected $fillable = [
    'command',
    'status',
    'output',
    'exception',
    'duration',
    'executed_at',
  ];
}
