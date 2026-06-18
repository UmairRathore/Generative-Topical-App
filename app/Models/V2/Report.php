<?php

namespace App\Models\V2;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasHashid;

    protected $table = 'v2_reports';

    protected $fillable = [
        'school_id',
        'branch_id',
        'student_id',
        'generated_by',
        'period_key',
        'period_label',
        'range_from',
        'range_to',
        'overall_avg',
        'tests_count',
        'source',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload'    => 'array',
            'range_from' => 'datetime',
            'range_to'   => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }
}
