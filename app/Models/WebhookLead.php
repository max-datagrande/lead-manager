<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLead extends Model
{
  protected $guarded = [];
  protected $table = 'webhook_leads';
  protected $primaryKey = 'id';
  protected $fillable = ['source', 'payload'];
  protected $casts = [
    'payload' => 'array',
  ];
}
