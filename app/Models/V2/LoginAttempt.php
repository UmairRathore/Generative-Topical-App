<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $table = 'v2_login_attempts';
    public $timestamps = false;

    protected $fillable = [
        'email',
        'ip_address',
        'guard',
        'successful',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'successful'   => 'boolean',
            'attempted_at' => 'datetime',
        ];
    }
}
