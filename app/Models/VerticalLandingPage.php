<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerticalLandingPage extends Model
{
    protected $fillable = [
        'name',
        'url',
        'is_external',
        'vertical_id',
        'company_id',
        'active',
        'user_id',
        'updated_user_id',
    ];

    // Belongs to a vertical
    public function vertical()
    {
        return $this->belongsTo(Vertical::class);
    }

    // Belongs to a company (optional)
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}