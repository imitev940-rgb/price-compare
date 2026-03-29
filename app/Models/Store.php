<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PriceHistory;
use App\Models\CompetitorLink;

class Store extends Model
{
    protected $fillable = [
        'name',
        'base_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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