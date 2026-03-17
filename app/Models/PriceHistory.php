<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceHistory extends Model
{
    protected $fillable = [
        'product_id',
        'store_id',
        'competitor_link_id',
        'our_price',
        'competitor_price',
        'difference',
        'percent_difference',
        'best_competitor',
        'position',
        'status',
        'checked_at',
    ];

    protected $casts = [
        'our_price' => 'decimal:2',
        'competitor_price' => 'decimal:2',
        'difference' => 'decimal:2',
        'percent_difference' => 'decimal:2',
        'checked_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function competitorLink()
    {
        return $this->belongsTo(CompetitorLink::class);
    }
}