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
        'scan_priority',
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

    public function getScanPriorityAttribute($value): string
    {
        return $value ?: 'normal';
    }
}