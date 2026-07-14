<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A canonical, version-identified official syllabus document (e.g. Cambridge
 * O Level Physics 5054, exams 2023-2025). Root of curriculum provenance:
 * learning objectives and authoring policies belong to a source, and the
 * source pins the exact PDF + extracted text by sha256. Internal-only model
 * (no routes), so no HasHashid.
 */
class SyllabusSource extends Model
{
    protected $table = 'v2_syllabus_sources';

    protected $fillable = [
        'subject_id',
        'syllabus_code',
        'version_label',
        'title',
        'exam_years',
        'source_pdf_path',
        'source_pdf_sha256',
        'extracted_text_path',
        'extracted_text_sha256',
        'extraction_tool',
        'structure_json',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'exam_years' => 'array',
            'structure_json' => 'array',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function learningObjectives(): HasMany
    {
        return $this->hasMany(LearningObjective::class, 'syllabus_source_id')
            ->orderBy('section_code')->orderBy('objective_number');
    }

    public function policies(): HasMany
    {
        return $this->hasMany(SyllabusPolicy::class, 'syllabus_source_id');
    }

    /** 'code@version' reference used by the v2:* syllabus commands, e.g. '5054@2023-2025'. */
    public function reference(): string
    {
        return "{$this->syllabus_code}@{$this->version_label}";
    }

    /** Resolve a '--source=5054@2023-2025' style reference (version optional when unambiguous). */
    public static function resolveReference(string $reference): ?self
    {
        [$code, $version] = array_pad(explode('@', $reference, 2), 2, null);

        $query = static::where('syllabus_code', $code);
        if ($version !== null && $version !== '') {
            return $query->where('version_label', $version)->first();
        }

        // Without a version the reference is only valid if exactly one row exists.
        $matches = $query->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** Official title of an intermediate header (e.g. '1.5' => 'Forces') from structure_json. */
    public function sectionTitle(string $code): ?string
    {
        foreach (($this->structure_json['sections'] ?? []) as $section) {
            if (($section['code'] ?? null) === $code) {
                return $section['title'] ?? null;
            }
        }

        return null;
    }
}
