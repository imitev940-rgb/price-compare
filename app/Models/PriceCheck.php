<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceCheck extends Model
{
    protected $fillable = [
        'competitor_link_id',
        'price',
        'availability',
        'checked_at',
        'notes',
    ];
}