<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One canonical, syllabus-source-scoped academic reference: a term, physical
 * quantity, definition, formal relationship (word + symbolic forms in ONE
 * row) or syllabus-prescribed constant. The single source of academic truth
 * that LOs, future Lessons, Fable packets, the Tutor and validators resolve
 * against - never re-derived from prose. Internal-only model, no HasHashid.
 */
class SyllabusReference extends Model
{
    protected $table = 'v2_syllabus_references';

    public const TYPE_TERM = 'term';

    public const TYPE_QUANTITY = 'quantity';

    public const TYPE_DEFINITION = 'definition';

    public const TYPE_RELATIONSHIP = 'relationship';

    public const TYPE_CONSTANT = 'constant';

    public const ALLOWED_TYPES = [
        self::TYPE_TERM,
        self::TYPE_QUANTITY,
        self::TYPE_DEFINITION,
        self::TYPE_RELATIONSHIP,
        self::TYPE_CONSTANT,
    ];

    // Same lifecycle as learning objectives / policies.
    public const STATUS_EXTRACTED = 'extracted';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'syllabus_source_id',
        'reference_type',
        'key',
        'title',
        'payload',
        'provenance',
        'status',
        'review_note',
        'validated_by',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'provenance' => 'array',
            'validated_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(SyllabusSource::class, 'syllabus_source_id');
    }

    public function objectiveLinks(): HasMany
    {
        return $this->hasMany(LearningObjectiveReference::class, 'syllabus_reference_id');
    }

    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VALIDATED);
    }

    /** 'type:key' handle used by curation files and resolver output, e.g. 'quantity:acceleration'. */
    public function handle(): string
    {
        return "{$this->reference_type}:{$this->key}";
    }
}
