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
        'model',
        'product_url',
        'our_price',
        'pcd_price',
        'is_active',
        'scan_priority',
        'discount_percent',
        'delivery_price',
        'new_price',
        'new_price_updated_at',
    ];

    protected $casts = [
        'our_price' => 'float',
        'pcd_price' => 'float',
        'is_active' => 'boolean',
        'discount_percent' => 'float',
        'delivery_price' => 'float',
        'new_price' => 'float',
        'new_price_updated_at' => 'datetime',
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
