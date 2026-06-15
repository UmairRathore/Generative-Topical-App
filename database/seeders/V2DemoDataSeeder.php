<?php

namespace Database\Seeders;

use App\Models\V2\ClassTeacher;
use App\Models\V2\Grade;
use App\Models\V2\School;
use App\Models\V2\SchoolAdmin;
use App\Models\V2\SchoolClass;
use App\Models\V2\SchoolSubject;
use App\Models\V2\Student;
use App\Models\V2\StudentEnrollment;
use App\Models\V2\Subject;
use App\Models\V2\Teacher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| V2DemoDataSeeder
|--------------------------------------------------------------------------
| Seeds a fully wired demo school so the exam flow is clickable end-to-end:
| school -> admin -> teacher -> class (AS-A Physics) -> 5 enrolled students.
| Known passwords + must_change_password=false so the demo has no friction.
| Idempotent. Requires V2SubjectsSeeder (Physics 9702) first.
*/
class V2DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $subject = Subject::where('code', '9702')->first();
        if (! $subject) {
            $this->command->error('Subject 9702 (Physics A Level) not found. Run V2SubjectsSeeder first.');
            return;
        }

        $pw = Hash::make('password');

        $school = School::updateOrCreate(
            ['contact_email' => 'demo@school.pk'],
            [
                'name' => 'Demo High School', 'campus_name' => 'Main Campus',
                'address' => 'Lahore', 'contact_phone' => '+92-300-1234567',
                'monthly_fee' => 100000, 'license_tier' => 'standard',
                'max_teachers' => 10, 'max_students' => 100,
                'status' => 'active', 'activated_at' => now(),
            ]
        );

        SchoolAdmin::updateOrCreate(
            ['email' => 'admin@demo.school.pk'],
            [
                'school_id' => $school->id, 'name' => 'Admin User', 'password' => $pw,
                'is_primary' => true, 'status' => 'active',
                'must_change_password' => false, 'session_version' => 1,
            ]
        );

        $grade = Grade::updateOrCreate(
            ['school_id' => $school->id, 'short_name' => 'AS'],
            ['name' => 'A Level Year 1 (AS)', 'sort_order' => 1, 'is_active' => true]
        );

        SchoolSubject::updateOrCreate(
            ['school_id' => $school->id, 'subject_id' => $subject->id, 'grade_id' => $grade->id],
            ['is_active' => true]
        );

        $teacher = Teacher::updateOrCreate(
            ['email' => 'teacher@demo.school.pk'],
            [
                'school_id' => $school->id, 'name' => 'Mr. Physics Teacher', 'password' => $pw,
                'status' => 'active', 'must_change_password' => false, 'session_version' => 1,
            ]
        );

        $class = SchoolClass::updateOrCreate(
            ['school_id' => $school->id, 'grade_id' => $grade->id, 'subject_id' => $subject->id, 'section' => 'A'],
            ['name' => 'AS-A Physics', 'is_active' => true]
        );

        ClassTeacher::updateOrCreate(
            ['class_id' => $class->id, 'teacher_id' => $teacher->id],
            ['school_id' => $school->id, 'is_primary' => true]
        );

        $students = [
            ['Ali Hassan', '2024-001'],
            ['Sara Ahmed', '2024-002'],
            ['Bilal Khan', '2024-003'],
            ['Fatima Malik', '2024-004'],
            ['Omar Sheikh', '2024-005'],
        ];

        foreach ($students as [$name, $roll]) {
            $student = Student::updateOrCreate(
                ['school_id' => $school->id, 'roll_number' => $roll],
                [
                    'name' => $name, 'email' => $roll.'@demo.school.pk', 'password' => $pw, 'grade' => 'AS',
                    'status' => 'active', 'must_change_password' => false, 'session_version' => 1,
                ]
            );
            StudentEnrollment::updateOrCreate(
                ['student_id' => $student->id, 'class_id' => $class->id],
                ['school_id' => $school->id, 'status' => 'active']
            );
        }

        $this->command->info('Demo data seeded for "Demo High School":');
        $this->command->table(['Role', 'Login', 'Password'], [
            ['School Admin', 'admin@demo.school.pk', 'password'],
            ['Teacher', 'teacher@demo.school.pk', 'password'],
            ['Student', 'roll 2024-001 … 2024-005 (email 2024-00N@demo.school.pk)', 'password'],
        ]);
        $this->command->line('Class: AS-A Physics (A Level Year 1 (AS)) · teacher assigned · 5 students enrolled.');
    }
}
