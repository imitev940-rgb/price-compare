<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $fillable = [
        'name',
        'base_url',
        'is_active',
    ];

    public function priceHistories()
{
    return $this->hasMany(PriceHistory::class);
}

    public function competitorLinks()
    {
        return $this->hasMany(CompetitorLink::class);
    }
}