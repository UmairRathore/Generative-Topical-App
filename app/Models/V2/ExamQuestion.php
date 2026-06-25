<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamQuestion extends Model
{
    protected $table = 'v2_exam_questions';

    protected $fillable = ['exam_id', 'question_id', 'question_version_id', 'sort_order', 'marks', 'is_voided', 'void_reason', 'voided_by', 'voided_at'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'marks' => 'integer', 'is_voided' => 'boolean', 'voided_at' => 'datetime'];
    }

    public function exam(): BelongsTo { return $this->belongsTo(Exam::class, 'exam_id'); }
    public function voider(): BelongsTo { return $this->belongsTo(Teacher::class, 'voided_by'); }
    // withTrashed: a frozen exam must still render its questions even if the source
    // question was later soft-deleted from the bank (historical papers stay intact).
    public function question(): BelongsTo { return $this->belongsTo(Question::class, 'question_id')->withTrashed(); }

    /** The exact frozen content version this exam used. */
    public function questionVersion(): BelongsTo { return $this->belongsTo(QuestionVersion::class, 'question_version_id'); }

    /**
     * The question AS THE STUDENT SAW IT — rendered from the frozen version
     * snapshot. Falls back to the live question if no version was stamped (e.g. an
     * exam built before versioning that the backfill somehow missed).
     */
    public function resolvedQuestion(): ?Question
    {
        $version = $this->relationLoaded('questionVersion')
            ? $this->questionVersion
            : ($this->question_version_id ? $this->questionVersion()->first() : null);

        return $version ? $version->toRenderableQuestion() : $this->question;
    }
}
