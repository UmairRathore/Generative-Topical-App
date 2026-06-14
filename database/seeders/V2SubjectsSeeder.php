<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class V2SubjectsSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            // O Level
            ['name' => 'English Language',       'code' => '1123', 'level' => 'O Level'],
            ['name' => 'English Literature',      'code' => '2010', 'level' => 'O Level'],
            ['name' => 'Mathematics',             'code' => '4024', 'level' => 'O Level'],
            ['name' => 'Additional Mathematics',  'code' => '4037', 'level' => 'O Level'],
            ['name' => 'Physics',                 'code' => '5054', 'level' => 'O Level'],
            ['name' => 'Chemistry',               'code' => '5070', 'level' => 'O Level'],
            ['name' => 'Biology',                 'code' => '5090', 'level' => 'O Level'],
            ['name' => 'Computer Science',        'code' => '2210', 'level' => 'O Level'],
            ['name' => 'Geography',               'code' => '2217', 'level' => 'O Level'],
            ['name' => 'History',                 'code' => '2147', 'level' => 'O Level'],
            ['name' => 'Islamiyat',               'code' => '2058', 'level' => 'O Level'],
            ['name' => 'Pakistan Studies',        'code' => '2059', 'level' => 'O Level'],
            ['name' => 'Urdu',                    'code' => '3247', 'level' => 'O Level'],

            // A Level
            ['name' => 'Mathematics (A Level)',   'code' => '9709', 'level' => 'A Level'],
            ['name' => 'Further Mathematics',     'code' => '9231', 'level' => 'A Level'],
            ['name' => 'Physics (A Level)',       'code' => '9702', 'level' => 'A Level'],
            ['name' => 'Chemistry (A Level)',     'code' => '9701', 'level' => 'A Level'],
            ['name' => 'Biology (A Level)',       'code' => '9700', 'level' => 'A Level'],
            ['name' => 'Computer Science (A Level)', 'code' => '9618', 'level' => 'A Level'],
            ['name' => 'Economics',               'code' => '9708', 'level' => 'A Level'],
        ];

        foreach ($subjects as $subject) {
            DB::table('v2_subjects')->updateOrInsert(
                ['code' => $subject['code']],
                array_merge($subject, [
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
