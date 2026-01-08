<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Integration extends Model
{
  protected $fillable = [
    'company_id',
    'name',
    'type',
    'is_active',
    'response_parser_config',
    'request_mapping_config',
    'user_id',
    'updated_user_id',
  ];

  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
    'response_parser_config' => 'array',
    'request_mapping_config' => 'array',
  ];

  /**
   * Get the environments for the integration.
   */
  public function environments()
  {
    return $this->hasMany(IntegrationEnvironment::class);
  }

  /**
   * Get the company that owns the integration.
   */
  public function company()
  {
    return $this->belongsTo(Company::class);
  }

  /**
   * Truncate the table.
   */
  public static function truncate()
  {
    DB::statement('TRUNCATE TABLE integrations RESTART IDENTITY CASCADE');
  }
}
