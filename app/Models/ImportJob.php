<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{
    protected $fillable = [
        'file_path',
        'original_name',
        'status',
        'total_rows',
        'processed_rows',
        'imported_count',
        'updated_count',
        'error_count',
        'last_error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function getProgressPercentAttribute(): int
    {
        if ((int) $this->total_rows <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->processed_rows / $this->total_rows) * 100));
    }
}