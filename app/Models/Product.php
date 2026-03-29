<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'ean',
        'brand',
        'product_url',
        'our_price',
        'is_active',
    ];

    protected $casts = [
        'our_price' => 'float',
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

    public function pazaruvajOffers()
    {
        return $this->hasMany(\App\Models\PazaruvajOffer::class);
    }
}