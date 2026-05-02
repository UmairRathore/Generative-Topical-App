<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paper extends Model
{
    protected $fillable = [
        'subject_id',
        'source_file',
        'paper_code',
        'paper_number',
        'variant',
        'session',
        'session_code',
        'year',
        'total_questions',
        'imported_questions_count',
        'demo_safe_questions_count',
        'raw_meta',
    ];

    protected $casts = [
        'raw_meta' => 'array',
        'year' => 'integer',
        'paper_number' => 'integer',
        'total_questions' => 'integer',
        'imported_questions_count' => 'integer',
        'demo_safe_questions_count' => 'integer',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
