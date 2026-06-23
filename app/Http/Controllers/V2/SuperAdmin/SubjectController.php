<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\Branch;
use App\Models\V2\School;
use App\Models\V2\Subject;
use App\Services\V2\AuditLogger;
use App\Services\V2\ExamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Super Admin: Subjects (unscoped)
|--------------------------------------------------------------------------
| Subject is a GLOBAL catalog table (no school_id), so no per-school binding
| guard is needed. School-scoped pages pass $school->id; platform pages pass
| null to aggregate across every school. The school + platform detail pages
| share one view (v2.super_admin.subjects.show), $school is null for platform.
*/
class SubjectController extends Controller
{
    /** Subject detail within a branch of a school. */
    public function show(School $school, Branch $branch, Subject $subject, ExamService $service): View
    {
        abort_unless($branch->school_id === $school->id, 404);

        $summary = collect($service->subjectWideRows($school->id, $branch->id))->firstWhere('id', $subject->id);
        $classes = collect($service->schoolClassRows($school->id, $branch->id))
            ->where('subject', $subject->name)->values()->all();

        return view('v2.super_admin.subjects.show', [
            'school'     => $school,
            'branch'     => $branch,
            'subject'    => $subject,
            'summary'    => $summary,
            'topicStats' => $service->subjectTopicStats($school->id, $subject->id, $branch->id),
            'classes'    => $classes,
        ]);
    }

    /**
     * Subject catalogue: every global subject with its status, usage and a
     * platform-wide performance roll-up. This is also where Super Admin turns a
     * subject on/off (is_active) — off subjects stay fully intact (questions,
     * history) but are no longer offered to schools via Subject::active().
     */
    public function platform(ExamService $service): View
    {
        $questions = DB::table('v2_questions')->selectRaw('subject_id, count(*) c')
            ->groupBy('subject_id')->pluck('c', 'subject_id');
        $schools = DB::table('v2_school_subjects')->selectRaw('subject_id, count(distinct school_id) c')
            ->groupBy('subject_id')->pluck('c', 'subject_id');
        $perf = collect($service->subjectPlatformRows())->keyBy('id');

        $subjects = Subject::orderBy('level')->orderBy('name')->get()
            ->map(fn ($s) => [
                'id'        => $s->id,
                'name'      => $s->name,
                'code'      => $s->code,
                'level'     => $s->level,
                'is_active' => $s->is_active,
                'questions' => (int) ($questions[$s->id] ?? 0),
                'schools'   => (int) ($schools[$s->id] ?? 0),
                'exams'     => (int) ($perf[$s->id]['exams'] ?? 0),
                'avg'       => $perf[$s->id]['avg'] ?? null,
            ]);

        return view('v2.super_admin.subjects.platform', [
            'subjects'    => $subjects,
            'activeCount' => $subjects->where('is_active', true)->count(),
        ]);
    }

    /** Turn a subject on/off platform-wide. Non-destructive — only flips is_active. */
    public function toggle(Subject $subject): RedirectResponse
    {
        $subject->update(['is_active' => ! $subject->is_active]);
        AuditLogger::record('subject.' . ($subject->is_active ? 'activated' : 'deactivated'), $subject, null, null);

        return back()->with('success', "{$subject->name} turned " . ($subject->is_active ? 'on' : 'off') . '.');
    }

    /** Platform-wide subject detail (all schools). */
    public function showPlatform(Subject $subject, ExamService $service): View
    {
        return view('v2.super_admin.subjects.show', [
            'school'     => null,
            'branch'     => null,
            'subject'    => $subject,
            'summary'    => collect($service->subjectPlatformRows())->firstWhere('id', $subject->id),
            'topicStats' => $service->subjectTopicStats(null, $subject->id),
            'classes'    => [],
        ]);
    }
}
