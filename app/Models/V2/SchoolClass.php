<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;


class SchoolClass extends Model
{
    protected $table = 'v2_classes';

    protected $fillable = [
        'school_id',
        'grade_id',
        'subject_id',
        'section',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('school', function (Builder $builder) {
            $adminGuard   = auth()->guard('v2_school_admin');
            $teacherGuard = auth()->guard('v2_teacher');
            if ($adminGuard->hasUser()) {
                $builder->where('v2_classes.school_id', $adminGuard->user()->school_id);
            } elseif ($teacherGuard->hasUser()) {
                $builder->where('v2_classes.school_id', $teacherGuard->user()->school_id);
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function classTeachers(): HasMany
    {
        return $this->hasMany(ClassTeacher::class, 'class_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'class_id');
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'v2_class_teachers', 'class_id', 'teacher_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'v2_student_enrollments', 'class_id', 'student_id')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getFullNameAttribute(): string
    {
        $grade   = $this->grade?->short_name ?? '';
        $section = $this->section ? "-{$this->section}" : '';
        $subject = $this->subject?->name ?? '';
        return trim("{$grade}{$section} {$subject}");
    }
}
