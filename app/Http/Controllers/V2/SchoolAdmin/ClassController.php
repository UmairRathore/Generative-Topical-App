<?php

namespace App\Http\Controllers\V2\SchoolAdmin;

use App\Models\V2\ClassTeacher;
use App\Models\V2\Grade;
use App\Models\V2\SchoolClass;
use App\Models\V2\SchoolSubject;
use App\Models\V2\Teacher;
use App\Services\V2\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassController extends BaseController
{
    public function index(): View
    {
        $classes = SchoolClass::with(['grade', 'subject', 'classTeachers.teacher'])->get();
        return view('v2.school_admin.classes.index', compact('classes'));
    }

    public function create(): View
    {
        $grades   = Grade::active()->get();
        $subjects = SchoolSubject::with('subject')->get();
        return view('v2.school_admin.classes.create', compact('grades', 'subjects'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'grade_id'   => 'required|exists:v2_grades,id',
            'subject_id' => 'nullable|exists:v2_subjects,id',
            'section'    => 'nullable|string|max:10',
            'name'       => 'nullable|string|max:150',
        ]);

        $grade = Grade::findOrFail($data['grade_id']);
        abort_if($grade->school_id !== $this->schoolId(), 403);

        $data['school_id'] = $this->schoolId();
        $data['is_active']  = true;

        $class = SchoolClass::create($data);

        AuditLogger::record('class.created', $class, $data, null);

        return redirect()->route('v2.school.classes.show', $class)
            ->with('success', 'Class created.');
    }

    public function show(SchoolClass $class): View
    {
        $this->authorizeClass($class);
        $class->load(['grade', 'subject', 'classTeachers.teacher', 'enrollments.student']);
        return view('v2.school_admin.classes.show', compact('class'));
    }

    public function edit(SchoolClass $class): View
    {
        $this->authorizeClass($class);
        $grades   = Grade::active()->get();
        $subjects = SchoolSubject::with('subject')->get();
        return view('v2.school_admin.classes.edit', compact('class', 'grades', 'subjects'));
    }

    public function update(Request $request, SchoolClass $class): RedirectResponse
    {
        $this->authorizeClass($class);

        $data = $request->validate([
            'grade_id'   => 'required|exists:v2_grades,id',
            'subject_id' => 'nullable|exists:v2_subjects,id',
            'section'    => 'nullable|string|max:10',
            'name'       => 'nullable|string|max:150',
        ]);

        $class->update($data);

        AuditLogger::record('class.updated', $class, $data, null);

        return redirect()->route('v2.school.classes.show', $class)
            ->with('success', 'Class updated.');
    }

    public function assignTeacher(Request $request, SchoolClass $class): RedirectResponse
    {
        $this->authorizeClass($class);

        $data = $request->validate([
            'teacher_id' => 'required|exists:v2_teachers,id',
            'is_primary' => 'boolean',
        ]);

        $teacher = Teacher::findOrFail($data['teacher_id']);
        abort_if($teacher->school_id !== $this->schoolId(), 403);

        ClassTeacher::firstOrCreate([
            'class_id'   => $class->id,
            'teacher_id' => $teacher->id,
        ], [
            'school_id'  => $this->schoolId(),
            'is_primary' => $data['is_primary'] ?? false,
        ]);

        AuditLogger::record('class.teacher_assigned', $class, ['teacher_id' => $teacher->id], null);

        return back()->with('success', 'Teacher assigned to class.');
    }

    public function removeTeacher(Request $request, SchoolClass $class): RedirectResponse
    {
        $this->authorizeClass($class);

        $data = $request->validate(['teacher_id' => 'required|exists:v2_teachers,id']);

        ClassTeacher::where([
            'class_id'   => $class->id,
            'teacher_id' => $data['teacher_id'],
            'school_id'  => $this->schoolId(),
        ])->delete();

        AuditLogger::record('class.teacher_removed', $class, $data, null);

        return back()->with('success', 'Teacher removed from class.');
    }

    private function authorizeClass(SchoolClass $class): void
    {
        abort_if($class->school_id !== $this->schoolId(), 403);
    }
}
