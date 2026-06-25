<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
| One material-error propagation run. The confirmed faulty `version_ids` captured
| here are the queued job's SINGLE source of truth — the job never re-derives the
| set. Idempotency: idempotency_key is unique (one run per review) and the child
| audit tables carry composite uniques so retries don't duplicate.
*/
class QualityPropagation extends Model
{
    protected $table = 'v2_quality_propagations';

    protected $fillable = [
        'quality_review_id', 'question_id', 'version_ids', 'confirmed_by', 'confirmed_at', 'status',
        'schools_count', 'branches_count', 'teachers_count', 'exams_count', 'attempts_count',
        'notifications_expected', 'notifications_sent', 'idempotency_key', 'started_at', 'completed_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'version_ids'  => 'array',
            'confirmed_at' => 'datetime',
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(QualityReview::class, 'quality_review_id');
    }

    public function pivots(): HasMany
    {
        return $this->hasMany(QualityPropagationPivot::class, 'propagation_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QualityPropagationAttempt::class, 'propagation_id');
    }
}
