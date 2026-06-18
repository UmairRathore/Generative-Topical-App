<?php

namespace App\Models\V2;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasHashid;

    protected $table = 'v2_exams';

    protected $fillable = [
        'school_id', 'class_id', 'subject_id', 'topic_id', 'created_by',
        'title', 'question_count', 'total_marks', 'duration_minutes',
        'year_from', 'year_to', 'shuffle', 'status', 'published_at',
        'released_at', 'available_from', 'available_until',
    ];

    protected function casts(): array
    {
        return [
            'shuffle'         => 'boolean',
            'question_count'  => 'integer',
            'total_marks'     => 'integer',
            'published_at'    => 'datetime',
            'released_at'     => 'datetime',
            'available_from'  => 'datetime',
            'available_until' => 'datetime',
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
        return $q->where('status', 'released');
    }

    /** Released AND inside [available_from, available_until] right now. */
    public function scopeAvailable(Builder $q): Builder
    {
        return $q->where('status', 'released')
            ->where(fn ($w) => $w->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn ($w) => $w->whereNull('available_until')->orWhere('available_until', '>=', now()));
    }

    /* ---- Lifecycle ------------------------------------------------------- */

    public function isReleased(): bool
    {
        return $this->status === 'released';
    }

    public function isDraft(): bool
    {
        return ! $this->isReleased();
    }

    /** Released but its window hasn't opened yet. */
    public function isScheduled(): bool
    {
        return $this->isReleased() && $this->available_from !== null && $this->available_from->isFuture();
    }

    /** Released and past its expiry. */
    public function isExpired(): bool
    {
        return $this->isReleased() && $this->available_until !== null && $this->available_until->isPast();
    }

    /** Released and takeable right now. */
    public function isLive(): bool
    {
        return $this->isReleased() && ! $this->isScheduled() && ! $this->isExpired();
    }

    /** One word for the current state: draft | scheduled | live | expired. */
    public function effectiveStatus(): string
    {
        return match (true) {
            $this->isDraft()     => 'draft',
            $this->isScheduled() => 'scheduled',
            $this->isExpired()   => 'expired',
            default              => 'live',
        };
    }
}
