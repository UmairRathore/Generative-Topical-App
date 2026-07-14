<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One numbered learning objective of a syllabus source - the canonical
 * curriculum coverage atom beneath the existing v2_subtopics leaf sections.
 *
 * Canonical identity = (syllabus_source_id, section_code, objective_number);
 * subtopic_id is only the application mapping. Machine-extracted rows are
 * UNTRUSTED ('extracted') until a human-reviewed overlay promotes them to
 * 'validated'; only validated rows may enter authoring context. Internal-only
 * model (no routes), so no HasHashid.
 */
class LearningObjective extends Model
{
    protected $table = 'v2_learning_objectives';

    // ── Review lifecycle ──────────────────────────────────────────────────
    // extracted = raw parser output, untrusted; validated = human-reviewed
    // canonical wording/equations/constraints; rejected = not an objective
    // (extraction artifact) or superseded.
    public const STATUS_EXTRACTED = 'extracted';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_EXTRACTED,
        self::STATUS_VALIDATED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'syllabus_source_id',
        'subtopic_id',
        'topic_number',
        'section_code',
        'section_title',
        'objective_number',
        'text',
        'sub_items',
        'equations',
        'constraints',
        'raw_extract',
        'provenance',
        'status',
        'review_note',
        'validated_by',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'topic_number' => 'integer',
            'objective_number' => 'integer',
            'sub_items' => 'array',
            'equations' => 'array',
            'constraints' => 'array',
            'raw_extract' => 'array',
            'provenance' => 'array',
            'validated_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(SyllabusSource::class, 'syllabus_source_id');
    }

    public function subtopic(): BelongsTo
    {
        return $this->belongsTo(Subtopic::class, 'subtopic_id');
    }

    /** Canonical-reference / policy-item mappings curated for this objective. */
    public function referenceLinks(): HasMany
    {
        return $this->hasMany(LearningObjectiveReference::class, 'learning_objective_id');
    }

    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VALIDATED);
    }

    /** Official human-readable reference, e.g. '1.2 #7'. */
    public function reference(): string
    {
        return "{$this->section_code} #{$this->objective_number}";
    }

    /** Constraint texts of one type ('exclusion' | 'depth' | 'condition'). */
    public function constraintTexts(string $type): array
    {
        return collect($this->constraints ?? [])
            ->where('type', $type)
            ->pluck('text')
            ->values()
            ->all();
    }
}
