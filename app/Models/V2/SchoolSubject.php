<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolSubject extends Model
{
    protected $table = 'v2_school_subjects';

    protected $fillable = [
        'school_id',
        'subject_id',
        'grade_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('school', function (Builder $builder) {
            $guard = auth()->guard('v2_school_admin');
            if ($guard->hasUser()) {
                $builder->where('school_id', $guard->user()->school_id);
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }
}
