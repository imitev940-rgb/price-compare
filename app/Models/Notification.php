<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'product_id',
        'store_id',
        'pazaruvaj_store',
        'type',
        'message',
        'old_price',
        'new_price',
        'price_change_percent',
        'is_read',
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
