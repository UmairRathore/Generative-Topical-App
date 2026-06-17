<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\School;
use App\Models\V2\Subject;
use App\Services\V2\ExamService;
use Illuminate\View\View;

/*
|--------------------------------------------------------------------------
| Super Admin — Subjects (unscoped)
|--------------------------------------------------------------------------
| Subject is a GLOBAL catalog table (no school_id), so no per-school binding
| guard is needed. School-scoped pages pass $school->id; platform pages pass
| null to aggregate across every school. The school + platform detail pages
| share one view (v2.super_admin.subjects.show), $school is null for platform.
*/
class SubjectController extends Controller
{
    /** Subject-wide list for one school. */
    public function index(School $school, ExamService $service): View
    {
        return view('v2.super_admin.subjects.index', [
            'school' => $school,
            'rows'   => $service->subjectWideRows($school->id),
        ]);
    }

    /** Subject detail within a school. */
    public function show(School $school, Subject $subject, ExamService $service): View
    {
        $summary = collect($service->subjectWideRows($school->id))->firstWhere('id', $subject->id);
        $classes = collect($service->schoolClassRows($school->id))
            ->where('subject', $subject->name)->values()->all();

        return view('v2.super_admin.subjects.show', [
            'school'     => $school,
            'subject'    => $subject,
            'summary'    => $summary,
            'topicStats' => $service->subjectTopicStats($school->id, $subject->id),
            'classes'    => $classes,
        ]);
    }

    /** Platform-wide subject rollup across all schools. */
    public function platform(ExamService $service): View
    {
        return view('v2.super_admin.subjects.platform', [
            'rows' => $service->subjectPlatformRows(),
        ]);
    }

    /** Platform-wide subject detail (all schools). */
    public function showPlatform(Subject $subject, ExamService $service): View
    {
        return view('v2.super_admin.subjects.show', [
            'school'     => null,
            'subject'    => $subject,
            'summary'    => collect($service->subjectPlatformRows())->firstWhere('id', $subject->id),
            'topicStats' => $service->subjectTopicStats(null, $subject->id),
            'classes'    => [],
        ]);
    }
}
