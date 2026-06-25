<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
| One Support Team review of a question. The teacher/student question flags are
| the "reports" feeding it; the review records the outcome (Phase 2) and links to
| the corrected version it produced. One open review per question at a time.
*/
class QualityReview extends Model
{
    protected $table = 'v2_quality_reviews';

    protected $fillable = [
        'question_id', 'status', 'outcome', 'reason_category', 'source_checked',
        'reviewed_by', 'reviewed_at', 'resulting_version_id',
    ];

    protected function casts(): array
    {
        return ['source_checked' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    public function resultingVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class, 'resulting_version_id');
    }

    /** The flag reports attached to this review. */
    public function reports(): HasMany
    {
        return $this->hasMany(QuestionFlag::class, 'quality_review_id');
    }
}
