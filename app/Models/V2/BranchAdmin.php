<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class BranchAdmin extends Authenticatable
{
    use Notifiable;

    protected $table = 'v2_branch_admins';
    protected $guard = 'v2_branch_admin';

    protected $fillable = [
        'school_id',
        'branch_id',
        'name',
        'email',
        'password',
        'phone',
        'status',
        'must_change_password',
        'session_version',
        'last_login_at',
        'last_login_ip',
        'created_by',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password'             => 'hashed',
            'must_change_password' => 'boolean',
            'last_login_at'        => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // School admin sees only their school's branch admins. Super admin unscoped.
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
