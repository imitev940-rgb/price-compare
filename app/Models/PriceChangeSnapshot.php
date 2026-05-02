<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceChangeSnapshot extends Model
{
    protected $fillable = [
        'user_id',
        'product_count',
        'data',
        'notes',
    ];

    protected $casts = [
        'data' => 'array',
        'product_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Извлича снимка за един продукт от data array-а
     */
    public function getProductChange(int $productId): ?array
    {
        foreach ($this->data ?? [] as $change) {
            if (($change['product_id'] ?? null) === $productId) {
                return $change;
            }
        }
        return null;
    }
}
