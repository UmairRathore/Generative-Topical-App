<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassTeacher extends Model
{
    protected $table = 'v2_class_teachers';

    protected $fillable = [
        'class_id',
        'teacher_id',
        'school_id',
        'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
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

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }
}
