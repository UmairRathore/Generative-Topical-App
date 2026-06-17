<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\AuditLog;
use App\Models\V2\Exam;
use App\Models\V2\ExamAttempt;
use App\Models\V2\School;
use App\Models\V2\Student;
use App\Models\V2\Teacher;
use App\Services\V2\ExamService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Super Admin — Dashboard
|--------------------------------------------------------------------------
| Platform-wide overview. Super Admin is NOT school-scoped: the v2 models'
| `school` global scope only triggers for the school-admin/teacher/student
| guards, so plain counts here span every school.
*/
class DashboardController extends Controller
{
    public function index(ExamService $service): View
    {
        $stats = [
            'schools'     => School::count(),
            'active'      => School::where('status', 'active')->count(),
            'suspended'   => School::where('status', 'suspended')->count(),
            'mrr'         => (int) School::where('status', 'active')->sum('monthly_fee'),
            'teachers'    => Teacher::count(),
            'students'    => Student::count(),
            'exams'       => Exam::count(),
            'submissions' => ExamAttempt::where('status', 'submitted')->count(),
        ];

        // Submissions + average score per school (single grouped query; nullif guards 0-question exams).
        $perf = DB::table('v2_exam_attempts as at')
            ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('at.status', 'submitted')
            ->selectRaw('e.school_id, count(*) subs, avg(at.score / nullif(at.total_questions,0)) * 100 avg_pct')
            ->groupBy('e.school_id')->get()->keyBy('school_id');

        $schools = School::withCount(['teachers', 'students'])
            ->orderByDesc('status')->orderByDesc('monthly_fee')->get()
            ->map(fn ($s) => [
                'id'       => $s->id,
                'name'     => $s->name,
                'city'     => $s->address,
                'status'   => $s->status,
                'tier'     => $s->license_tier,
                'fee'      => (int) $s->monthly_fee,
                'teachers' => $s->teachers_count,
                'students' => $s->students_count,
                'subs'     => (int) ($perf[$s->id]->subs ?? 0),
                'avg'      => isset($perf[$s->id]) && $perf[$s->id]->avg_pct !== null ? (int) round($perf[$s->id]->avg_pct) : null,
            ]);

        $recentLogs = AuditLog::orderByDesc('created_at')->limit(8)->get();

        // Cross-school rollups for the dashboard panels (top rows; full lists on their own pages).
        $gradeRollup   = array_slice($service->gradePlatformRows(), 0, 5);
        $subjectRollup = array_slice($service->subjectPlatformRows(), 0, 5);
        $topicRollup   = array_slice($service->topicWideStats(null), 0, 6);

        return view('v2.super_admin.dashboard.index', compact(
            'stats', 'schools', 'recentLogs', 'gradeRollup', 'subjectRollup', 'topicRollup'
        ));
    }
}
