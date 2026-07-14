<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A syllabus-level authoring language/policy document (command words,
 * symbols & units, mathematical requirements, Language of Measurement,
 * graph/data conventions, nomenclature rules, assessment model) curated
 * from a canonical syllabus source. Constraints on HOW lessons are written,
 * never student-facing content. Internal-only model, no HasHashid.
 */
class SyllabusPolicy extends Model
{
    protected $table = 'v2_syllabus_policies';

    public const TYPE_COMMAND_WORDS = 'command_words';

    public const TYPE_SYMBOLS_UNITS = 'symbols_units';

    public const TYPE_MATH_REQUIREMENTS = 'math_requirements';

    public const TYPE_MEASUREMENT_LANGUAGE = 'measurement_language';

    public const TYPE_GRAPH_DATA_CONVENTIONS = 'graph_data_conventions';

    public const TYPE_NOMENCLATURE_CONVENTIONS = 'nomenclature_conventions';

    public const TYPE_ASSESSMENT_MODEL = 'assessment_model';

    public const ALLOWED_TYPES = [
        self::TYPE_COMMAND_WORDS,
        self::TYPE_SYMBOLS_UNITS,
        self::TYPE_MATH_REQUIREMENTS,
        self::TYPE_MEASUREMENT_LANGUAGE,
        self::TYPE_GRAPH_DATA_CONVENTIONS,
        self::TYPE_NOMENCLATURE_CONVENTIONS,
        self::TYPE_ASSESSMENT_MODEL,
    ];

    // Same lifecycle as LearningObjective: extracted (untrusted) -> validated | rejected.
    public const STATUS_EXTRACTED = 'extracted';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'syllabus_source_id',
        'policy_type',
        'title',
        'content',
        'provenance',
        'status',
        'review_note',
        'validated_by',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'provenance' => 'array',
            'validated_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(SyllabusSource::class, 'syllabus_source_id');
    }

    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VALIDATED);
    }
}
