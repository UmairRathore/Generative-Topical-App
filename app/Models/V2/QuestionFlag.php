<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionFlag extends Model
{
    protected $table = 'v2_question_flags';

    /** Reason value => human label. Drives the teacher dropdown + admin display. */
    public const REASONS = [
        'incomplete_text' => 'Missing or cut-off text',
        'image_issue'     => 'Image cropped or missing',
        'wrong_answer'    => 'Answer looks wrong',
        'formatting'      => 'Formatting (e.g. 1/3 shown as “1 3”)',
        'other'           => 'Something else',
    ];

    protected $fillable = [
        'question_id',
        'school_id',
        'flagged_by_teacher_id',
        'reason',
        'note',
        'screenshot_path',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        // withTrashed so the queue still renders a flag whose question was archived/trashed.
        return $this->belongsTo(Question::class, 'question_id')->withTrashed();
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'flagged_by_teacher_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst(str_replace('_', ' ', (string) $this->reason));
    }
}
