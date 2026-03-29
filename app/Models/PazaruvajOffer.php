<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PazaruvajOffer extends Model
{
    protected $fillable = [
        'product_id',
        'competitor_link_id',
        'store_name',
        'offer_title',
        'offer_url',
        'price',
        'position',
        'is_lowest',
        'checked_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_lowest' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function competitorLink()
    {
        return $this->belongsTo(CompetitorLink::class);
    }
}