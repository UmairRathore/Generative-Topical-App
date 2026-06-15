<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $table = 'v2_exams';

    protected $fillable = [
        'school_id', 'class_id', 'subject_id', 'topic_id', 'created_by',
        'title', 'question_count', 'total_marks', 'duration_minutes',
        'year_from', 'year_to', 'shuffle', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'shuffle'         => 'boolean',
            'question_count'  => 'integer',
            'total_marks'     => 'integer',
            'published_at'    => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('school', function (Builder $builder) {
            foreach (['v2_school_admin', 'v2_teacher', 'v2_student'] as $g) {
                if (auth()->guard($g)->hasUser()) {
                    $builder->where('v2_exams.school_id', auth()->guard($g)->user()->school_id);
                    break;
                }
            }
        });
    }

    public function school(): BelongsTo { return $this->belongsTo(School::class, 'school_id'); }
    public function schoolClass(): BelongsTo { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class, 'subject_id'); }
    public function topic(): BelongsTo { return $this->belongsTo(Topic::class, 'topic_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(Teacher::class, 'created_by'); }

    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class, 'exam_id')->orderBy('sort_order');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'v2_exam_questions', 'exam_id', 'question_id')
            ->withPivot(['sort_order', 'marks'])
            ->orderByPivot('sort_order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class, 'exam_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }
}
