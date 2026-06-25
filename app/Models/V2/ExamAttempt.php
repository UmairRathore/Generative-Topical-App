<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    protected $table = 'v2_exam_attempts';

    protected $fillable = [
        'exam_id', 'student_id', 'school_id', 'status',
        'score', 'total_questions', 'started_at', 'submitted_at',
        'adjusted_score', 'adjusted_total', 'adjusted_at',
    ];

    protected function casts(): array
    {
        return [
            'score'           => 'integer',
            'total_questions' => 'integer',
            'adjusted_score'  => 'integer',
            'adjusted_total'  => 'integer',
            'adjusted_at'     => 'datetime',
            'started_at'      => 'datetime',
            'submitted_at'    => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('school', function (Builder $builder) {
            foreach (['v2_school_admin', 'v2_teacher', 'v2_student'] as $g) {
                if (auth()->guard($g)->hasUser()) {
                    $builder->where('v2_exam_attempts.school_id', auth()->guard($g)->user()->school_id);
                    break;
                }
            }
        });
    }

    public function exam(): BelongsTo { return $this->belongsTo(Exam::class, 'exam_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class, 'student_id'); }
    public function answers(): HasMany { return $this->hasMany(ExamAnswer::class, 'attempt_id'); }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function getPercentageAttribute(): int
    {
        if (! $this->total_questions || $this->score === null) {
            return 0;
        }
        return (int) round($this->score / $this->total_questions * 100);
    }

    /** Time from start to submit, formatted "m:ss" (null if not finished). */
    public function getTimeTakenAttribute(): ?string
    {
        if (! $this->started_at || ! $this->submitted_at) {
            return null;
        }
        $s = (int) abs($this->started_at->diffInSeconds($this->submitted_at));

        return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    }
}
