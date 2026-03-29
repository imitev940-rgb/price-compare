<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'product_id',
        'type',
        'message',
        'is_read',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}