<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Form extends Model
{
  protected $fillable = [
    'name',
    'description',
  ];
  protected static function booted(): void
  {
    static::creating(function ($form) {
      $form->name = ucwords(strtolower($form->name));
      $form->user_id = Auth::id();
    });
    static::updating(function ($form) {
      $form->name = ucwords(strtolower($form->name));
      $form->user_id = Auth::id();
    });
  }
  public function fields()
  {
    return $this->belongsToMany(Field::class)
      ->withTimestamps();
  }
}
