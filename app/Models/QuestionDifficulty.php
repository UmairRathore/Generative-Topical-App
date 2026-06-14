<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionDifficulty extends Model
{
    protected $fillable = [
        'question_id',
        'overall',
        'score',
        'reasoning_complexity',
        'calculation_complexity',
        'conceptual_depth',
        'multi_topic_dependency',
        'visual_interpretation',
        'trap_probability',
        'estimated_time_seconds',
        'primary_difficulty_driver',
        'difficulty_reason',
        'needs_review',
        'classifier_version',
    ];

    protected $casts = [
        'score' => 'float',
        'reasoning_complexity' => 'float',
        'calculation_complexity' => 'float',
        'conceptual_depth' => 'float',
        'multi_topic_dependency' => 'float',
        'visual_interpretation' => 'float',
        'trap_probability' => 'float',
        'estimated_time_seconds' => 'integer',
        'difficulty_reason' => 'array',
        'needs_review' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
