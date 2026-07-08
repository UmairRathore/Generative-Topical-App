<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTutorQuizQuestion extends Model
{
    protected $table = 'v2_ai_tutor_quiz_questions';

    protected $fillable = [
        'quiz_id', 'sort_order', 'stem', 'options', 'correct_option', 'explanation',
    ];

    protected function casts(): array
    {
        return [
            'options'    => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function quiz(): BelongsTo { return $this->belongsTo(AiTutorQuiz::class, 'quiz_id'); }
}
