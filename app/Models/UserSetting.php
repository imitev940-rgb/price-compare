<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'language',
        'theme',
        'notifications_enabled',
        'refresh_interval',
    ];

    protected $casts = [
        'notifications_enabled' => 'boolean',
        'refresh_interval' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}