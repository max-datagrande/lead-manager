<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vertical extends Model
{
    protected $fillable = [
        'name',
        'description',
        'active',
        'user_id',
        'updated_user_id',
    ];

    // One vertical can have many landing pages
    public function landingPages()
    {
        return $this->hasMany(VerticalLandingPage::class);
    }
}