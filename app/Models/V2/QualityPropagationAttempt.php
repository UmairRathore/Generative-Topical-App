<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;

/*
| Audit row: one per submitted attempt on an exam a propagation recomputed, with
| the before/after score totals. Unique on (propagation_id, attempt_id) so retries
| never double-record.
*/
class QualityPropagationAttempt extends Model
{
    public $timestamps = false;

    protected $table = 'v2_quality_propagation_attempts';

    protected $fillable = [
        'propagation_id', 'exam_id', 'attempt_id', 'student_id',
        'score_before', 'total_before', 'score_after', 'total_after', 'released', 'notified',
    ];

    protected function casts(): array
    {
        return ['released' => 'boolean', 'notified' => 'boolean'];
    }
}
