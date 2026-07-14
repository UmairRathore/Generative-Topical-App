<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A hash-bound human sign-off of a scoped content foundation (additive -
 * never rewrites row-level machine/curation provenance). Verification
 * recomputes the foundation fingerprint from a freshly compiled packet:
 * mismatch = the reviewed content changed = sign-off STALE. Internal-only
 * model, no HasHashid.
 */
class ReviewSignoff extends Model
{
    protected $table = 'v2_review_signoffs';

    protected $fillable = [
        'syllabus_source_id',
        'scope',
        'reviewer',
        'artifact_path',
        'packet_sha256',
        'foundation_fingerprint',
        'plan_sha256',
        'lesson_sha256',
        'notes',
        'signed_at',
    ];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(SyllabusSource::class, 'syllabus_source_id');
    }

    /** Canonical-truth sections of a packet that a human sign-off binds to. */
    public const FOUNDATION_SECTIONS = [
        'identity', 'target_coverage', 'out_of_scope_curriculum',
        'direct_academic_truth', 'supporting_academic_truth', 'allowed_reference_handles',
        'hard_constraints', 'policy_slices', 'pedagogical_evidence', 'relevant_widgets',
    ];

    /** Deterministic fingerprint over the canonical-truth subset of a packet. */
    public static function foundationFingerprint(array $packet): string
    {
        $subset = [];
        foreach (self::FOUNDATION_SECTIONS as $section) {
            $subset[$section] = $packet[$section] ?? null;
        }

        return hash('sha256', json_encode($subset, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** Scope string for one lesson foundation, derived from the packet itself. */
    public static function scopeFor(array $packet): string
    {
        $numbers = implode(',', array_column($packet['target_coverage']['objectives'], 'number'));

        return 'stage-a-foundation:'
            .$packet['identity']['syllabus_code'].'@'.$packet['identity']['syllabus_version']
            .':'.$packet['identity']['leaf_section']['section_code']
            .':'.$numbers;
    }
}
