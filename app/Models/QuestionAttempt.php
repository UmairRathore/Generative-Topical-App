<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionAttempt extends Model
{
    protected $fillable = [
        'test_session_id',
        'question_id',
        'user_id',
        'selected_answer',
        'correct_answer',
        'is_correct',
        'answered_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_correct' => 'boolean',
        'answered_at' => 'datetime',
    ];

    public function testSession(): BelongsTo
    {
        return $this->belongsTo(TestSession::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
