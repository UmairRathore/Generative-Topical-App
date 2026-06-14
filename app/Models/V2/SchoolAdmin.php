<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class SchoolAdmin extends Authenticatable
{
    use Notifiable;

    protected $table = 'v2_school_admins';
    protected $guard = 'v2_school_admin';

    protected $fillable = [
        'school_id',
        'name',
        'email',
        'password',
        'phone',
        'is_primary',
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
            'is_primary'          => 'boolean',
            'last_login_at'       => 'datetime',
        ];
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
}
