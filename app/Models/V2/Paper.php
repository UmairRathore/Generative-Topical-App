<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paper extends Model
{
    protected $table = 'v2_papers';

    protected $fillable = [
        'subject_id',
        'source_file',
        'source_paper',
        'subject_code',
        'paper_code',
        'paper_number',
        'variant',
        'session_code',
        'session_label',
        'year',
        'total_questions',
    ];

    protected function casts(): array
    {
        return [
            'paper_number'    => 'integer',
            'year'            => 'integer',
            'total_questions' => 'integer',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'paper_id')->orderBy('question_number');
    }
}
