<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\School;
use App\Models\V2\SchoolClass;
use App\Services\V2\ExamService;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Super Admin — Class detail (unscoped)
|--------------------------------------------------------------------------
| Mirrors SchoolAdmin\AnalyticsController::classDetail. The per-student matrix
| links each student through the shared student_matrix partial, which builds
| route($studentRoute, $studentId) with a SINGLE positional arg. The super
| admin student route needs {school} too, so we bind it as a URL default for
| the duration of this request.
*/
class ClassController extends Controller
{
    public function show(School $school, SchoolClass $class, ExamService $service): View
    {
        abort_unless($class->school_id === $school->id, 404);

        $class->load(['grade', 'subject']);
        URL::defaults(['school' => $school->id]);

        return view('v2.super_admin.classes.show', [
            'school'     => $school,
            'class'      => $class,
            'topicStats' => $service->classTopicStats($class),
            'matrix'     => $service->classStudentMatrix($class),
            'split'      => $service->examKindSplit(null, $class->id),
        ]);
    }
}
