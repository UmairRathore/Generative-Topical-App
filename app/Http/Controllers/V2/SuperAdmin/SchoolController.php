<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\School;
use App\Services\V2\ExamService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Super Admin — Schools
|--------------------------------------------------------------------------
| Read-only listing + per-school detail across the whole platform. The School
| model carries no global scope (root tenant table), and the ExamService stat
| helpers take an explicit school id, so super admin sees every school.
|
| (Create / edit / activate / suspend are intentionally not built here yet.)
*/
class SchoolController extends Controller
{
    public function index(): View
    {
        $perf = DB::table('v2_exam_attempts as at')
            ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('at.status', 'submitted')
            ->selectRaw('e.school_id, count(*) subs, avg(at.score / nullif(at.total_questions,0)) * 100 avg_pct')
            ->groupBy('e.school_id')->get()->keyBy('school_id');

        $schools = School::withCount(['teachers', 'students', 'classes'])
            ->orderBy('name')->get()
            ->map(fn ($s) => [
                'id'       => $s->id,
                'name'     => $s->name,
                'city'     => $s->address,
                'email'    => $s->contact_email,
                'status'   => $s->status,
                'tier'     => $s->license_tier,
                'fee'      => (int) $s->monthly_fee,
                'teachers' => $s->teachers_count,
                'students' => $s->students_count,
                'classes'  => $s->classes_count,
                'subs'     => (int) ($perf[$s->id]->subs ?? 0),
                'avg'      => isset($perf[$s->id]) && $perf[$s->id]->avg_pct !== null ? (int) round($perf[$s->id]->avg_pct) : null,
            ]);

        $mrr = (int) School::where('status', 'active')->sum('monthly_fee');

        return view('v2.super_admin.schools.index', compact('schools', 'mrr'));
    }

    public function show(School $school, ExamService $service): View
    {
        return view('v2.super_admin.schools.show', [
            'school'     => $school,
            'overview'   => $service->schoolOverview($school->id),
            'topicStats' => $service->schoolTopicStats($school->id),
            'teachers'   => $service->schoolTeacherRows($school->id),
            'classes'    => $service->schoolClassRows($school->id),
        ]);
    }
}
