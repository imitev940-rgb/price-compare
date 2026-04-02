<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportError extends Model
{
    protected $fillable = [
        'import_job_id',
        'row_number',
        'row_data',
        'error_message',
    ];

    public function importJob()
    {
        return $this->belongsTo(ImportJob::class);
    }
}