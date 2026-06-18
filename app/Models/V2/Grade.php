<?php

namespace App\Models\V2;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    use HasHashid;

    protected $table = 'v2_grades';

    protected $fillable = [
        'school_id',
        'name',
        'short_name',
        'sort_order',
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

        static::addGlobalScope('ordered', function (Builder $builder) {
            $builder->orderBy('sort_order')->orderBy('name');
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function schoolSubjects(): HasMany
    {
        return $this->hasMany(SchoolSubject::class, 'grade_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'grade_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
