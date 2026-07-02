<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only history for a Mistake Bank entry. */
class StudentMistakeEvent extends Model
{
    protected $table = 'v2_student_mistake_events';

    public const WRONG = 'wrong';
    public const REVIEWED = 'reviewed';
    public const ASSET_VIEWED = 'asset_viewed';
    public const MARKED_MASTERED = 'marked_mastered';
    public const REOPENED = 'reopened';
    public const CONFIDENCE_UPDATED = 'confidence_updated';

    protected $fillable = [
        'student_mistake_id', 'student_id', 'question_id', 'attempt_id', 'exam_id',
        'event_type', 'selected_option', 'correct_option', 'meta_json', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'meta_json'   => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function mistake(): BelongsTo
    {
        return $this->belongsTo(StudentMistake::class, 'student_mistake_id');
    }
}
