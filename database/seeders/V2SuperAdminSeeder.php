<?php

namespace Database\Seeders;

use App\Models\V2\SuperAdmin;
use Illuminate\Database\Seeder;

class V2SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        SuperAdmin::updateOrCreate(
            ['email' => 'super@gt.v2'],
            [
                'name'                 => 'GT Super Admin',
                'password'             => 'password',
                'must_change_password' => false,
            ]
        );
    }
}
