<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionTopicTag extends Model
{
    protected $fillable = [
        'question_id',
        'syllabus_topic_id',
        'syllabus_subtopic_id',
        'syllabus_learning_objective_id',
        'relevance',
        'score',
        'reason',
        'needs_review',
        'classifier_version',
    ];

    protected $casts = [
        'score' => 'float',
        'needs_review' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(SyllabusTopic::class, 'syllabus_topic_id');
    }

    public function subtopic(): BelongsTo
    {
        return $this->belongsTo(SyllabusSubtopic::class, 'syllabus_subtopic_id');
    }

    public function learningObjective(): BelongsTo
    {
        return $this->belongsTo(SyllabusLearningObjective::class, 'syllabus_learning_objective_id');
    }
}
