<?php

namespace App\Models\V2;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasHashid;

    protected $table = 'v2_branches';

    protected $fillable = ['school_id', 'name', 'address', 'status'];

    protected static function booted(): void
    {
        // School admin sees only their school's branches; a branch admin only
        // their own branch. Super admin is unscoped.
        static::addGlobalScope('school', function (Builder $builder) {
            if (auth()->guard('v2_school_admin')->hasUser()) {
                $builder->where('v2_branches.school_id', auth()->guard('v2_school_admin')->user()->school_id);
            } elseif (auth()->guard('v2_branch_admin')->hasUser()) {
                $builder->where('v2_branches.id', auth()->guard('v2_branch_admin')->user()->branch_id);
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'branch_id');
    }
}
