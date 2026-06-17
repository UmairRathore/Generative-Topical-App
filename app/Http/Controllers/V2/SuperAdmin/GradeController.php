<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\Grade;
use App\Models\V2\School;
use App\Services\V2\ExamService;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Super Admin — Grades (unscoped)
|--------------------------------------------------------------------------
| Grade-wide analytics within a school, plus a cross-school platform rollup.
| Route-model binding is NOT school-scoped for the super admin guard, so each
| detail action asserts the grade belongs to the school in the URL.
*/
class GradeController extends Controller
{
    /** Grade-wide list for one school. */
    public function index(School $school, ExamService $service): View
    {
        return view('v2.super_admin.grades.index', [
            'school' => $school,
            'rows'   => $service->gradeWideRows($school->id),
        ]);
    }

    /** Grade detail within a school. */
    public function show(School $school, Grade $grade, ExamService $service): View
    {
        abort_unless($grade->school_id === $school->id, 404);

        $summary = collect($service->gradeWideRows($school->id))->firstWhere('id', $grade->id);
        $classes = collect($service->schoolClassRows($school->id))
            ->where('grade', $grade->name)->values()->all();

        return view('v2.super_admin.grades.show', [
            'school'     => $school,
            'grade'      => $grade,
            'summary'    => $summary,
            'topicStats' => $service->gradeTopicStats($school->id, $grade->id),
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
