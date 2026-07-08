<?php

namespace App\Models\V2;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One persistent Mistake Bank row per (student, question). Never deleted;
 * repeats increment mistake_count and append a StudentMistakeEvent.
 */
class StudentMistake extends Model
{
    use HasHashid;

    protected $table = 'v2_student_mistakes';

    public const STATUS_NEW = 'new';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_PRACTICED = 'practiced';
    public const STATUS_MASTERED = 'mastered';
    public const STATUS_ARCHIVED = 'archived';

    /** Statuses the student considers "done" (Hide Completed filters these out). */
    public const RESOLVED_STATUSES = [self::STATUS_MASTERED, self::STATUS_ARCHIVED];

    protected $fillable = [
        'student_id', 'school_id', 'question_id',
        'subject_id', 'topic_id', 'subtopic_id', 'difficulty', 'year', 'source_paper',
        'first_wrong_attempt_id', 'latest_wrong_attempt_id', 'latest_exam_id',
        'selected_option', 'correct_option',
        'mistake_count', 'first_wrong_at', 'last_wrong_at',
        'status', 'review_count', 'last_reviewed_at', 'confidence',
        'resolved_at', 'archived_at', 'mastered_at',
    ];

    protected function casts(): array
    {
        return [
            'mistake_count'   => 'integer',
            'review_count'    => 'integer',
            'confidence'      => 'integer',
            'year'            => 'integer',
            'first_wrong_at'  => 'datetime',
            'last_wrong_at'   => 'datetime',
            'last_reviewed_at' => 'datetime',
            'resolved_at'     => 'datetime',
            'archived_at'     => 'datetime',
            'mastered_at'     => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Same-school isolation for the authenticated portal guard (mirrors Exam / ExamAttempt).
        static::addGlobalScope('school', function (Builder $builder) {
            foreach (['v2_school_admin', 'v2_teacher', 'v2_student'] as $g) {
                if (auth()->guard($g)->hasUser()) {
                    $builder->where('v2_student_mistakes.school_id', auth()->guard($g)->user()->school_id);
                    break;
                }
            }
        });
    }

    public function student(): BelongsTo { return $this->belongsTo(Student::class, 'student_id'); }
    public function aiChats(): HasMany { return $this->hasMany(AiTutorChat::class, 'student_mistake_id'); }
    public function aiQuizzes(): HasMany { return $this->hasMany(AiTutorQuiz::class, 'student_mistake_id'); }
    public function question(): BelongsTo { return $this->belongsTo(Question::class, 'question_id'); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class, 'subject_id'); }
    public function topic(): BelongsTo { return $this->belongsTo(Topic::class, 'topic_id'); }
    public function subtopic(): BelongsTo { return $this->belongsTo(Subtopic::class, 'subtopic_id'); }
    public function latestExam(): BelongsTo { return $this->belongsTo(Exam::class, 'latest_exam_id'); }
    public function events(): HasMany { return $this->hasMany(StudentMistakeEvent::class, 'student_mistake_id'); }

    public function isMastered(): bool
    {
        return $this->status === self::STATUS_MASTERED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }
}
