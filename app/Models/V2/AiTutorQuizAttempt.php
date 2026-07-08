<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The student's answers/score for a tutor mini quiz. A learning signal only -
 * completely separate from official exam results.
 */
class AiTutorQuizAttempt extends Model
{
    protected $table = 'v2_ai_tutor_quiz_attempts';

    protected $fillable = [
        'quiz_id', 'student_id', 'school_id',
        'answers', 'score', 'total', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'answers'      => 'array',
            'score'        => 'integer',
            'total'        => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function quiz(): BelongsTo { return $this->belongsTo(AiTutorQuiz::class, 'quiz_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class, 'student_id'); }
}
