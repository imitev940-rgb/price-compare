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
        'competitor_product_name',
        'matched_title',
        'last_price',
        'last_checked_at',
        'is_active',
        'is_auto_found',
        'search_status',
        'match_score',
        'last_error',
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
        'is_active' => 'boolean',
        'is_auto_found' => 'boolean',
        'last_price' => 'decimal:2',
        'match_score' => 'decimal:2',
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