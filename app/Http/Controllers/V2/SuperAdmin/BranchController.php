<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\Branch;
use App\Models\V2\School;
use App\Services\V2\ExamService;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Super Admin: Branch detail (unscoped)
|--------------------------------------------------------------------------
| The branch sits between a school and its teachers/classes/students. Every
| roll-up here is narrowed to this branch via the ExamService $branchId
| argument, so a school's branches stay isolated from one another.
*/
class BranchController extends Controller
{
    public function show(School $school, Branch $branch, ExamService $service): View
    {
        abort_unless($branch->school_id === $school->id, 404);

        return view('v2.super_admin.branches.show', [
            'school'     => $school,
            'branch'     => $branch,
            'overview'   => $service->schoolOverview($school->id, $branch->id),
            'topicStats' => $service->schoolTopicStats($school->id, $branch->id),
            'grades'     => $service->gradeWideRows($school->id, $branch->id),
            'subjects'   => $service->subjectWideRows($school->id, $branch->id),
            'teachers'   => $service->schoolTeacherRows($school->id, $branch->id),
            'classes'    => $service->schoolClassRows($school->id, $branch->id),
        ]);
    }
}
