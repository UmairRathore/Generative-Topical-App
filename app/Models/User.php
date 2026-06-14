<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable // implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isStudent(): bool
    {
        return $this->role === UserRole::Student;
    }

    public function isTeacher(): bool
    {
        return $this->role === UserRole::Teacher;
    }

    /** True for school admins AND platform super admins. */
    public function isAdmin(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::SuperAdmin], true);
    }

    /** Strict: only school-level admin. */
    public function isSchoolAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** Platform super admin (Generative Topical staff). */
    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            UserRole::SuperAdmin => 'Super Admin',
            UserRole::Admin      => 'School Admin',
            UserRole::Teacher    => 'Teacher',
            UserRole::Student    => 'Student',
            default              => 'Member',
        };
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }
}
