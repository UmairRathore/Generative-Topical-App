<?php

namespace App\Http\Controllers\V2\Teacher;

use App\Http\Controllers\Controller;
use App\Services\V2\ExamService;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Teacher - Class analytics / stats
|--------------------------------------------------------------------------
| Across the teacher's own exams + assigned classes: class comparison,
| score distribution, per-topic performance, exam trend and the students
| who need attention. Scoped to exams the teacher created.
*/
class StatsController extends Controller
{
    public function index(ExamService $service): View
    {
        $teacher  = auth('v2_teacher')->user();
        $classIds = $teacher->classes()->pluck('v2_classes.id')->all();

        $overview   = array_merge($service->teacherOverview($teacher->id), $service->teacherEngagement($teacher->id));
        $topics     = collect($service->teacherTopicStats($teacher->id))->sortByDesc('percent')->values()->all();

        // Per-class rows for this teacher only (reuse the school-wide builder, filter to my classes).
        $classes = collect($service->schoolClassRows($teacher->school_id))
            ->whereIn('id', $classIds)->values()->all();

        $distribution = $service->teacherScoreDistribution($teacher->id);
        $examTrend    = $service->teacherExamTrend($teacher->id, 15);
        $students     = collect($service->teacherStudentPerformance($teacher->id));

        // Students needing attention: low average, or active-but-silent (0 attempts).
        $needsAttention = $students->filter(
            fn ($s) => ($s['avg'] !== null && $s['avg'] < 50) || $s['attempts'] === 0
        )->values();

        return view('v2.teacher.stats.index', [
            'teacher'        => $teacher,
            'overview'       => $overview,
            'classes'        => $classes,
            'topics'         => $topics,
            'distribution'   => $distribution,
            'examTrend'      => $examTrend,
            'students'       => $students,
            'needsAttention' => $needsAttention,
        ]);
    }
}
