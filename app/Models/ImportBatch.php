<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $fillable = [
        'source_path',
        'source_file',
        'status',
        'total_papers',
        'total_questions',
        'imported_questions',
        'skipped_questions',
        'errors',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'errors' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'total_papers' => 'integer',
        'total_questions' => 'integer',
        'imported_questions' => 'integer',
        'skipped_questions' => 'integer',
    ];
}
