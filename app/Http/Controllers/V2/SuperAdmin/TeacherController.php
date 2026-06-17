<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\Exam;
use App\Models\V2\School;
use App\Models\V2\Teacher;
use App\Services\V2\ExamService;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Super Admin — Teacher detail (unscoped)
|--------------------------------------------------------------------------
| Mirrors SchoolAdmin\AnalyticsController::teacher, but the super admin is not
| school-scoped, so the teacher is asserted to belong to the URL's school.
*/
class TeacherController extends Controller
{
    public function show(School $school, Teacher $teacher, ExamService $service): View
    {
        abort_unless($teacher->school_id === $school->id, 404);

        $classes = $teacher->classes()->with(['grade', 'subject'])
            ->withCount(['enrollments as student_count' => fn ($e) => $e->where('status', 'active')])
            ->get();

        $examCounts = Exam::where('created_by', $teacher->id)
            ->selectRaw('class_id, count(*) c')->groupBy('class_id')->pluck('c', 'class_id');

        return view('v2.super_admin.teachers.show', [
            'school'     => $school,
            'teacher'    => $teacher,
            'topicStats' => $service->teacherTopicStats($teacher->id),
            'classes'    => $classes,
            'examCounts' => $examCounts,
        ]);
    }
}
