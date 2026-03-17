<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitorLink extends Model
{
    protected $fillable = [
        'product_id',
        'store_id',
        'product_url',
        'last_price',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}