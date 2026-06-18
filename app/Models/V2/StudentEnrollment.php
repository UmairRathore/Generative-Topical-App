<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends Model
{
    protected $table = 'v2_student_enrollments';

    protected $fillable = [
        'student_id',
        'class_id',
        'school_id',
        'branch_id',
        'status',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('school', function (Builder $builder) {
            $adminGuard   = auth()->guard('v2_school_admin');
            $teacherGuard = auth()->guard('v2_teacher');
            $branchGuard  = auth()->guard('v2_branch_admin');
            if ($adminGuard->hasUser()) {
                $builder->where('school_id', $adminGuard->user()->school_id);
            } elseif ($teacherGuard->hasUser()) {
                $builder->where('school_id', $teacherGuard->user()->school_id);
            } elseif ($branchGuard->hasUser()) {
                $builder->where('branch_id', $branchGuard->user()->branch_id);
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
