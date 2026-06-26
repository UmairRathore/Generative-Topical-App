<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\Branch;
use App\Models\V2\Exam;
use App\Models\V2\ExamAttempt;
use App\Models\V2\Question;
use App\Models\V2\School;
use App\Models\V2\Student;
use App\Models\V2\Teacher;
use App\Services\V2\ExamService;
use App\Services\V2\PlatformStatsService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Super Admin - Platform analytics / stats
|--------------------------------------------------------------------------
| Platform-wide: MRR + growth trends, school engagement & churn risk, daily
| activity, most-attempted topics and the hardest questions. Not school-scoped.
*/
class StatsController extends Controller
{
    public function index(ExamService $exam, PlatformStatsService $platform): View
    {
        $stats = [
            'schools'     => School::where('status', 'active')->count(),
            'branches'    => Branch::count(),
            'teachers'    => Teacher::count(),
            'students'    => Student::count(),
            'questions'   => Question::count(),
            'exams'       => Exam::count(),
            'submissions' => ExamAttempt::where('status', 'submitted')->count(),
            'reports'     => DB::table('v2_reports')->count(),
            'mrr'         => (int) School::where('status', 'active')->sum('monthly_fee'),
        ];

        $topTopics = collect($exam->topicWideStats(null))
            ->sortByDesc('total')->take(10)->values()->all();

        return view('v2.super_admin.stats.index', [
            'stats'      => $stats,
            'mrrTrend'   => $platform->mrrTrend(12),
            'growth'     => $platform->growth(12),
            'engagement' => $platform->schoolEngagement(),
            'daily'      => $platform->dailyActivity(30),
            'topTopics'  => $topTopics,
            'hardest'    => $platform->hardestQuestions(8),
        ]);
    }
}
