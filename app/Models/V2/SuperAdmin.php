<?php

namespace App\Models\V2;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class SuperAdmin extends Authenticatable
{
    use Notifiable;

    protected $table = 'v2_super_admins';
    protected $guard = 'v2_super_admin';

    protected $fillable = [
        'name',
        'email',
        'password',
        'must_change_password',
        'last_login_at',
        'last_login_ip',
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
}
