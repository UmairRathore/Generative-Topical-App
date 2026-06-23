<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\Branch;
use App\Models\V2\School;
use App\Models\V2\SchoolClass;
use App\Services\V2\ExamService;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Super Admin: Class detail (unscoped)
|--------------------------------------------------------------------------
| Mirrors SchoolAdmin\AnalyticsController::classDetail. Reached through a
| branch; the class is asserted to belong to that branch (and school). The
| per-student matrix is passed [$school, $branch] explicitly so its links keep
| the full hierarchy and use each model's hashid.
*/
class ClassController extends Controller
{
    public function show(School $school, Branch $branch, SchoolClass $class, ExamService $service): View
    {
        abort_unless($branch->school_id === $school->id, 404);
        abort_unless($class->branch_id === $branch->id, 404);

        $class->load(['grade', 'subject']);

        return view('v2.super_admin.classes.show', [
            'school'     => $school,
            'branch'     => $branch,
            'class'      => $class,
            'topicStats' => $service->classTopicStats($class),
            'matrix'     => $service->classStudentMatrix($class),
            'split'      => $service->examKindSplit(null, $class->id),
        ]);
    }
}
