<?php

namespace App\Models\V2;

use App\Services\V2\QuestionSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
| An immutable snapshot of a question's content at one point in time. Never edited
| after creation - corrections create a NEW version and advance the question's
| current_version_id pointer.
*/
class QuestionVersion extends Model
{
    protected $table = 'v2_question_versions';

    protected $fillable = [
        'question_id', 'version_number', 'snapshot', 'correct_answer',
        'change_summary', 'review_reason', 'quality_review_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'version_number' => 'integer'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    public function qualityReview(): BelongsTo
    {
        return $this->belongsTo(QualityReview::class, 'quality_review_id');
    }

    /** Render this exact frozen version as a non-persisted Question (with options/images). */
    public function toRenderableQuestion(): Question
    {
        return QuestionSnapshot::hydrate($this->snapshot ?? [], (int) $this->question_id);
    }
}
