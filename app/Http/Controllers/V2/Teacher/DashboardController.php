<?php

namespace App\Http\Controllers\V2\Teacher;

use App\Http\Controllers\Controller;
use App\Services\V2\ExamService;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Teacher - Dashboard / analytics hub
|--------------------------------------------------------------------------
| Locked to the signed-in teacher: their topic performance, single-vs-mixed
| exam split, and their classes (drill into class → student → graded paper).
*/
class DashboardController extends Controller
{
    public function index(ExamService $service): View
    {
        $teacher = auth('v2_teacher')->user();
        $classIds = $teacher->classes()->pluck('v2_classes.id')->all();

        $classes = collect($service->schoolClassRows($teacher->school_id))
            ->whereIn('id', $classIds)->values()->all();

        return view('v2.teacher.dashboard.index', [
            'teacher'    => $teacher,
            'overview'   => $service->teacherOverview($teacher->id),
            'topicStats' => $service->teacherTopicStats($teacher->id),
            'split'      => $service->examKindSplit(null, null, $teacher->id),
            'classes'    => $classes,
        ]);
    }
}
