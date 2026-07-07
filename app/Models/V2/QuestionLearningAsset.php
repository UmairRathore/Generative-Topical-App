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

    // ── Review lifecycle ─────────────────────────────────────────────────────
    // AI generation happens OUTSIDE the app (Claude Code / workflow) and writes
    // assets as `draft`. A super-admin then approves / edits / rejects / hides
    // each one. Students ONLY ever see super-admin-blessed content.
    public const STATUS_DRAFT = 'draft';          // ai_generated_pending_review
    public const STATUS_APPROVED = 'approved';    // approved_by_superadmin
    public const STATUS_EDITED = 'edited';        // edited_by_superadmin (student-visible)
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_HIDDEN = 'hidden';
    public const STATUS_FAILED = 'failed_validation';

    // Legacy statuses in older rows — treated as pending; kept only for mapping.
    public const STATUS_GENERATED = 'generated';
    public const STATUS_REVIEWED = 'reviewed';

    /** The ONLY statuses a student may ever see (super-admin blessed). */
    public const VISIBLE_STATUSES = [self::STATUS_APPROVED, self::STATUS_EDITED];

    /** Awaiting super-admin review. */
    public const PENDING_STATUSES = [self::STATUS_DRAFT, self::STATUS_GENERATED, self::STATUS_REVIEWED];

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
