<?php

namespace Database\Seeders;

use App\Models\V2\Branch;
use App\Models\V2\BranchAdmin;
use App\Models\V2\School;
use App\Models\V2\SchoolClass;
use App\Models\V2\Student;
use App\Models\V2\StudentEnrollment;
use App\Models\V2\Teacher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| V2 Branch demo data
|--------------------------------------------------------------------------
| Gives each school campus branches, a branch admin per branch, and back-fills
| branch_id on classes / teachers / students / enrollments. The flagship (Sage)
| gets two branches (so branch isolation is demonstrable); others get one.
| Idempotent — safe to re-run.  All branch admins use password "password".
*/
class V2BranchSeeder extends Seeder
{
    public function run(): void
    {
        foreach (School::orderBy('id')->get() as $school) {
            $classes = SchoolClass::where('school_id', $school->id)->orderBy('id')->get();
            if ($classes->isEmpty()) {
                continue;
            }

            $isSage = str_contains(strtolower($school->name), 'sage');
            $branchNames = $isSage ? ['Gulberg Campus', 'DHA Campus'] : ['Main Campus'];

            $branches = [];
            foreach ($branchNames as $name) {
                $branches[] = Branch::firstOrCreate(
                    ['school_id' => $school->id, 'name' => $name],
                    ['status' => 'active']
                );
            }

            // Assign each class to a branch (split round-robin for Sage, else all to the one branch).
            foreach ($classes->values() as $idx => $class) {
                $branch = $branches[$isSage ? ($idx % count($branches)) : 0];

                $class->branch_id = $branch->id;
                $class->save();

                StudentEnrollment::where('class_id', $class->id)->update(['branch_id' => $branch->id]);

                $studentIds = StudentEnrollment::where('class_id', $class->id)->pluck('student_id');
                Student::whereIn('id', $studentIds)->update(['branch_id' => $branch->id]);

                $teacherIds = DB::table('v2_class_teachers')->where('class_id', $class->id)->pluck('teacher_id');
                Teacher::whereIn('id', $teacherIds)->update(['branch_id' => $branch->id]);
            }

            // Branch admin per branch (e.g. gulberg@thesage.edu.pk).
            $domain = Str::after($school->contact_email ?? '', '@') ?: (Str::slug($school->name).'.edu.pk');
            foreach ($branches as $branch) {
                $slug = Str::slug(Str::before($branch->name, ' '));
                BranchAdmin::firstOrCreate(
                    ['email' => $slug.'@'.$domain],
                    [
                        'school_id'            => $school->id,
                        'branch_id'            => $branch->id,
                        'name'                 => $branch->name.' Admin',
                        'password'             => 'password',
                        'status'               => 'active',
                        'must_change_password' => false,
                    ]
                );
            }
        }
    }
}
