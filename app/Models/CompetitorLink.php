<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Models\Store;
use App\Models\PriceHistory;

class CompetitorLink extends Model
{
    protected $fillable = [
        'product_id',
        'store_id',
        'product_url',
        'last_price',
        'last_checked_at',
        'is_active',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function priceHistories()
    {
        return $this->hasMany(PriceHistory::class);
    }
}