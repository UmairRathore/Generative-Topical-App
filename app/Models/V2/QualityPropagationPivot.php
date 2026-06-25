<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;

/*
| Audit row: one per exam-question pivot a propagation considered, recording what
| happened to it (voided / already excluded / skipped). Unique on
| (propagation_id, exam_question_id) so retries never double-record.
*/
class QualityPropagationPivot extends Model
{
    public $timestamps = false;

    protected $table = 'v2_quality_propagation_pivots';

    protected $fillable = [
        'propagation_id', 'exam_question_id', 'exam_id', 'school_id', 'branch_id',
        'question_version_id', 'action', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
