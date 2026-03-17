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
        'our_price',
        'is_active',
    ];

    public function competitorLinks()
    {
        return $this->hasMany(CompetitorLink::class);
    }
    
}