<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
| One Support Team review of a question. The teacher/student question flags are
| the "reports" feeding it; the review records the Support outcome (Phase 2) and
| links to the corrected version it produced. Exactly ONE open review per question
| at a time. A material error is additionally marked propagation_pending for the
| Phase 3 historical-propagation job.
*/
class QualityReview extends Model
{
    protected $table = 'v2_quality_reviews';

    /** Support decision outcomes (Phase 2). "correct" is the dismissal equivalent. */
    public const OUTCOMES = ['correct', 'cosmetic', 'material'];

    protected $fillable = [
        'question_id', 'status', 'outcome', 'propagation_status', 'reason_category',
        'source_checked', 'reviewed_by', 'reviewed_at', 'resulting_version_id',
    ];

    protected function casts(): array
    {
        return ['source_checked' => 'array', 'reviewed_at' => 'datetime'];
    }

    /**
     * Ensure exactly one OPEN review for a question and attach its still-unresolved
     * flags as the review's reports. Idempotent: reuses the open review if one
     * exists, so re-reporting never spawns duplicates. Called when a question enters
     * review (teacher send-for-review / bank flag) and by the backfill migration.
     */
    public static function openFor(int $questionId): self
    {
        $review = static::firstOrCreate(['question_id' => $questionId, 'status' => 'open']);

        QuestionFlag::where('question_id', $questionId)
            ->whereNull('quality_review_id')
            ->whereIn('status', ['open', 'escalated'])
            ->update(['quality_review_id' => $review->id]);

        return $review;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeDecided(Builder $query): Builder
    {
        return $query->where('status', 'decided');
    }

    public function question(): BelongsTo
    {
        // withTrashed so the queue still renders a review whose question was archived/trashed.
        return $this->belongsTo(Question::class, 'question_id')->withTrashed();
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
