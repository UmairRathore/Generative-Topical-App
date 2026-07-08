<?php

namespace App\Models\V2;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An AI-generated mini quiz inside a tutor chat. Temporary tutor practice
 * content - never part of the official question bank or exam results.
 */
class AiTutorQuiz extends Model
{
    use HasHashid;

    protected $table = 'v2_ai_tutor_quizzes';

    public const STATUS_READY = 'ready';
    public const STATUS_ATTEMPTED = 'attempted';

    protected $fillable = [
        'chat_id', 'student_id', 'school_id',
        'question_id', 'student_mistake_id',
        'title', 'status', 'model',
    ];

    protected static function booted(): void
    {
        // Same-school isolation for the authenticated portal guard (mirrors AiTutorChat).
        static::addGlobalScope('school', function (Builder $builder) {
            foreach (['v2_school_admin', 'v2_teacher', 'v2_student'] as $g) {
                if (auth()->guard($g)->hasUser()) {
                    $builder->where('v2_ai_tutor_quizzes.school_id', auth()->guard($g)->user()->school_id);
                    break;
                }
            }
        });
    }

    public function chat(): BelongsTo { return $this->belongsTo(AiTutorChat::class, 'chat_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class, 'student_id'); }
    public function questions(): HasMany { return $this->hasMany(AiTutorQuizQuestion::class, 'quiz_id')->orderBy('sort_order'); }
    public function attempts(): HasMany { return $this->hasMany(AiTutorQuizAttempt::class, 'quiz_id'); }
}
