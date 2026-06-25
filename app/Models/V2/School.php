<?php

namespace App\Models\V2;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class School extends Model
{
    use HasHashid;

    protected $table = 'v2_schools';

    protected $fillable = [
        'name',
        'campus_name',
        'address',
        'contact_email',
        'contact_phone',
        'monthly_fee',
        'license_tier',
        'apply_retro_void',
        'max_teachers',
        'max_students',
        'status',
        'activated_at',
        'suspended_at',
        'suspension_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'monthly_fee'      => 'decimal:2',
            'apply_retro_void' => 'boolean',
            'activated_at'     => 'datetime',
            'suspended_at'     => 'datetime',
        ];
    }

    public function schoolAdmins(): HasMany
    {
        return $this->hasMany(SchoolAdmin::class, 'school_id');
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class, 'school_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'school_id');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'school_id');
    }

    public function schoolSubjects(): HasMany
    {
        return $this->hasMany(SchoolSubject::class, 'school_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'school_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('status', 'suspended');
    }
}
