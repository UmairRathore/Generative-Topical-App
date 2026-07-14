<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One LO -> canonical-reference (or LO -> policy-item) mapping with a role.
 * Exactly one target per row: syllabus_reference_id XOR (policy_type +
 * policy_item_key) - enforced by the import command, asserted in isCoherent().
 */
class LearningObjectiveReference extends Model
{
    protected $table = 'v2_learning_objective_references';

    public const ROLE_DEFINES = 'defines';

    public const ROLE_INTRODUCES = 'introduces';

    public const ROLE_REQUIRES = 'requires';

    public const ROLE_USES = 'uses';

    public const ROLE_CALCULATES_WITH = 'calculates_with';

    public const ROLE_INTERPRETS = 'interprets';

    public const ROLE_APPLIES = 'applies';

    // Supporting academic truth: the reference is needed to TEACH this LO but
    // is established by a sibling LO - it must never imply coverage of that
    // sibling. Resolves into supporting_references, never direct references.
    public const ROLE_PREREQUISITE = 'prerequisite';

    public const ALLOWED_ROLES = [
        self::ROLE_DEFINES,
        self::ROLE_INTRODUCES,
        self::ROLE_REQUIRES,
        self::ROLE_USES,
        self::ROLE_CALCULATES_WITH,
        self::ROLE_INTERPRETS,
        self::ROLE_APPLIES,
        self::ROLE_PREREQUISITE,
    ];

    protected $fillable = [
        'learning_objective_id',
        'syllabus_reference_id',
        'policy_type',
        'policy_item_key',
        'role',
        'note',
    ];

    public function learningObjective(): BelongsTo
    {
        return $this->belongsTo(LearningObjective::class, 'learning_objective_id');
    }

    public function reference(): BelongsTo
    {
        return $this->belongsTo(SyllabusReference::class, 'syllabus_reference_id');
    }

    /** Exactly one target: a canonical reference XOR a keyed policy item. */
    public function isCoherent(): bool
    {
        $hasReference = $this->syllabus_reference_id !== null;
        $hasPolicyItem = $this->policy_type !== null && $this->policy_item_key !== null;

        return $hasReference xor $hasPolicyItem;
    }
}
