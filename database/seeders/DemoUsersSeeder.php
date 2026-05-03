<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    /**
     * Four demo profiles for clicking through the platform.
     * All four share the password "password".
     */
    public function run(): void
    {
        $accounts = [
            [
                'email' => 'student@gt.test',
                'name'  => 'Ayesha Khan',
                'role'  => UserRole::Student,
            ],
            [
                'email' => 'teacher@gt.test',
                'name'  => 'Dr. Saima Iqbal',
                'role'  => UserRole::Teacher,
            ],
            [
                'email' => 'school@gt.test',
                'name'  => 'Aisha Rehman',
                'role'  => UserRole::Admin,        // school-level admin
            ],
            [
                'email' => 'super@gt.test',
                'name'  => 'GT Super Admin',
                'role'  => UserRole::SuperAdmin,   // platform-wide
            ],
        ];

        foreach ($accounts as $a) {
            User::updateOrCreate(
                ['email' => $a['email']],
                [
                    'name'              => $a['name'],
                    'password'          => Hash::make('password'),
                    'role'              => $a['role'],
                    'email_verified_at' => now(),
                ],
            );
        }

        $this->command?->info('Demo users seeded:');
        $this->command?->table(
            ['Email', 'Password', 'Role'],
            collect($accounts)->map(fn ($a) => [$a['email'], 'password', $a['role']->value])->all()
        );
    }
}
