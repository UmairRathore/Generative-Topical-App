<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reusable per-question learning content, attached to the canonical question_id.
 * Phase 1 only reads/displays assets that already exist with a visible status;
 * generation (manual or AI) is a later phase.
 */
class QuestionLearningAsset extends Model
{
    protected $table = 'v2_question_learning_assets';

    /** The only asset types the system recognises (route/service reject anything else). */
    public const ALLOWED_TYPES = [
        'worked_solution', 'option_explanation', 'flashcards', 'memcards',
        'mermaid', 'interactive_widget', 'revision_notes', 'common_mistakes',
    ];

    /** Types the Learning Hub can render as text this phase. */
    public const DISPLAYABLE_TYPES = [
        'worked_solution', 'option_explanation', 'revision_notes', 'common_mistakes',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_GENERATED = 'generated';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_APPROVED = 'approved';

    /** Statuses a student is allowed to see (draft is never shown). */
    public const VISIBLE_STATUSES = [self::STATUS_GENERATED, self::STATUS_REVIEWED, self::STATUS_APPROVED];

    protected $fillable = [
        'question_id', 'asset_type', 'asset_key', 'title', 'content', 'payload_json',
        'format', 'status', 'source_hash', 'generated_by', 'reviewed_by', 'reviewed_at', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'reviewed_at'  => 'datetime',
            'approved_at'  => 'datetime',
        ];
    }

    /** Only assets a student may see. */
    public function scopeVisible(Builder $q): Builder
    {
        return $q->whereIn('status', self::VISIBLE_STATUSES);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
