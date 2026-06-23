<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\Branch;
use App\Models\V2\Grade;
use App\Models\V2\School;
use App\Services\V2\ExamService;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Super Admin: Grades (unscoped)
|--------------------------------------------------------------------------
| Grade-wide analytics within a school, plus a cross-school platform rollup.
| Route-model binding is NOT school-scoped for the super admin guard, so each
| detail action asserts the grade belongs to the school in the URL.
*/
class GradeController extends Controller
{
    /** Grade detail within a branch of a school. */
    public function show(School $school, Branch $branch, Grade $grade, ExamService $service): View
    {
        abort_unless($branch->school_id === $school->id, 404);
        abort_unless($grade->school_id === $school->id, 404);

        $summary = collect($service->gradeWideRows($school->id, $branch->id))->firstWhere('id', $grade->id);
        $classes = collect($service->schoolClassRows($school->id, $branch->id))
            ->where('grade', $grade->name)->values()->all();

        return view('v2.super_admin.grades.show', [
            'school'     => $school,
            'branch'     => $branch,
            'grade'      => $grade,
            'summary'    => $summary,
            'topicStats' => $service->gradeTopicStats($school->id, $grade->id, $branch->id),
            'classes'    => $classes,
        ]);
    }

    /** Platform-wide grade rollup (grouped by grade name across all schools). */
    public function platform(ExamService $service): View
    {
        return view('v2.super_admin.grades.platform', [
            'rows' => $service->gradePlatformRows(),
        ]);
    }
}
