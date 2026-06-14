<?php

namespace Database\Seeders;

use App\Models\V2\School;
use App\Models\V2\SchoolAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class V2DemoSchoolSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::firstOrCreate(
            ['contact_email' => 'school@demo.v2'],
            [
                'name'              => 'Demo Academy',
                'campus_name'       => 'Main Campus',
                'address'           => '1 Demo Street, Karachi',
                'contact_phone'     => '+92-300-0000000',
                'monthly_fee'       => '5000.00',
                'license_tier'      => 'standard',
                'max_teachers'      => 50,
                'max_students'      => 500,
                'status'            => 'active',
                'activated_at'      => now(),
            ]
        );

        SchoolAdmin::firstOrCreate(
            ['email' => 'admin@demo.v2'],
            [
                'school_id'            => $school->id,
                'name'                 => 'Demo School Admin',
                'password'             => Hash::make('password'),
                'status'               => 'active',
                'must_change_password' => false,
                'session_version'      => 1,
            ]
        );

        $this->command->info("Demo school created: school@demo.v2");
        $this->command->info("School admin: admin@demo.v2 / password");
        $this->command->info("Login at: /v2/school/login");
    }
}
