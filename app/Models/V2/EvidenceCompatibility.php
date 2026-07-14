<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An explicit, scoped decision that prior-cycle questions may serve as
 * NON-AUTHORITATIVE assessment evidence for a newer syllabus cycle, for one
 * leaf section. Never curriculum authority; never a global year override.
 * Stale the moment its diff basis changes. Internal-only model, no HasHashid.
 */
class EvidenceCompatibility extends Model
{
    protected $table = 'v2_evidence_compatibilities';

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_ACTIVE = 'active';

    protected $fillable = [
        'old_syllabus_source_id',
        'new_syllabus_source_id',
        'section_code',
        'basis',
        'diff_artifact_path',
        'diff_sha256',
        'status',
        'reviewed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return ['basis' => 'array'];
    }

    public function oldSource(): BelongsTo
    {
        return $this->belongsTo(SyllabusSource::class, 'old_syllabus_source_id');
    }

    public function newSource(): BelongsTo
    {
        return $this->belongsTo(SyllabusSource::class, 'new_syllabus_source_id');
    }

    /** Stale when the diff artifact this decision rests on changed or vanished. */
    public function isStale(): bool
    {
        $path = is_file($this->diff_artifact_path)
            ? $this->diff_artifact_path
            : base_path($this->diff_artifact_path);

        return ! is_file($path) || hash_file('sha256', $path) !== $this->diff_sha256;
    }

    /** Compact provenance reference embedded in compiled evidence items. */
    public function decisionReference(): string
    {
        return "evidence-compat#{$this->id}@".substr($this->diff_sha256, 0, 16);
    }
}
