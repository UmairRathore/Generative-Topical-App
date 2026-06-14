<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Teacher extends Authenticatable
{
    use Notifiable;

    protected $table = 'v2_teachers';
    protected $guard = 'v2_teacher';

    protected $fillable = [
        'school_id',
        'name',
        'email',
        'password',
        'phone',
        'employee_id',
        'status',
        'must_change_password',
        'last_login_at',
        'last_login_ip',
        'session_version',
        'created_by',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password'            => 'hashed',
            'must_change_password'=> 'boolean',
            'last_login_at'       => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('school', function (Builder $builder) {
            $adminGuard   = auth()->guard('v2_school_admin');
            $teacherGuard = auth()->guard('v2_teacher');
            if ($adminGuard->hasUser()) {
                $builder->where('school_id', $adminGuard->user()->school_id);
            } elseif ($teacherGuard->hasUser()) {
                $builder->where('school_id', $teacherGuard->user()->school_id);
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function classTeachers(): HasMany
    {
        return $this->hasMany(ClassTeacher::class, 'teacher_id');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'v2_class_teachers', 'teacher_id', 'class_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
