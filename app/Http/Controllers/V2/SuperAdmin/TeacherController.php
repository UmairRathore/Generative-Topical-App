<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\Branch;
use App\Models\V2\Exam;
use App\Models\V2\School;
use App\Models\V2\Teacher;
use App\Services\V2\ExamService;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Super Admin: Teacher detail (unscoped)
|--------------------------------------------------------------------------
| Mirrors SchoolAdmin\AnalyticsController::teacher. Reached through a branch;
| the teacher is asserted to belong to that branch (and school).
*/
class TeacherController extends Controller
{
    public function show(School $school, Branch $branch, Teacher $teacher, ExamService $service): View
    {
        abort_unless($branch->school_id === $school->id, 404);
        abort_unless($teacher->branch_id === $branch->id, 404);

        $classes = $teacher->classes()->with(['grade', 'subject'])
            ->withCount(['enrollments as student_count' => fn ($e) => $e->where('status', 'active')])
            ->get();

        $examCounts = Exam::where('created_by', $teacher->id)
            ->selectRaw('class_id, count(*) c')->groupBy('class_id')->pluck('c', 'class_id');

        return view('v2.super_admin.teachers.show', [
            'school'     => $school,
            'branch'     => $branch,
            'teacher'    => $teacher,
            'topicStats' => $service->teacherTopicStats($teacher->id),
            'classes'    => $classes,
            'examCounts' => $examCounts,
        ]);
    }
}
