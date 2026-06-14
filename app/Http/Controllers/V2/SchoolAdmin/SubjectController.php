<?php

namespace App\Http\Controllers\V2\SchoolAdmin;

use App\Models\V2\Grade;
use App\Models\V2\SchoolSubject;
use App\Models\V2\Subject;
use App\Services\V2\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectController extends BaseController
{
    public function index(): View
    {
        $allSubjects     = Subject::active()->orderBy('name')->get();
        $grades          = Grade::active()->get();
        $schoolSubjects  = SchoolSubject::with(['subject', 'grade'])->get();

        $assignedMatrix = [];
        foreach ($schoolSubjects as $ss) {
            $assignedMatrix[$ss->subject_id][$ss->grade_id] = $ss;
        }

        return view('v2.school_admin.subjects.index', compact('allSubjects', 'grades', 'assignedMatrix'));
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject_id' => 'required|exists:v2_subjects,id',
            'grade_id'   => 'required|exists:v2_grades,id',
        ]);

        $grade = Grade::findOrFail($data['grade_id']);
        abort_if($grade->school_id !== $this->schoolId(), 403);

        $ss = SchoolSubject::firstOrCreate([
            'school_id'  => $this->schoolId(),
            'subject_id' => $data['subject_id'],
            'grade_id'   => $data['grade_id'],
        ], ['is_active' => true]);

        AuditLogger::record('subject.assigned', $ss, $data, null);

        return back()->with('success', 'Subject assigned to grade.');
    }

    public function remove(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject_id' => 'required|exists:v2_subjects,id',
            'grade_id'   => 'required|exists:v2_grades,id',
        ]);

        SchoolSubject::where([
            'school_id'  => $this->schoolId(),
            'subject_id' => $data['subject_id'],
            'grade_id'   => $data['grade_id'],
        ])->delete();

        AuditLogger::record('subject.removed', null, $data, null);

        return back()->with('success', 'Subject removed from grade.');
    }
}
