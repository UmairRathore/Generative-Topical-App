<?php

namespace App\Http\Controllers\V2\Teacher;

use App\Http\Controllers\Controller;
use App\Models\V2\Exam;
use App\Models\V2\SchoolClass;
use App\Models\V2\Student;
use App\Models\V2\StudentEnrollment;
use App\Services\V2\ExamService;

class ClassController extends Controller
{
    private function teacher()
    {
        return auth('v2_teacher')->user();
    }

    public function index()
    {
        $teacher = $this->teacher();

        $classes = $teacher->classes()
            ->with(['grade', 'subject'])
            ->withCount(['enrollments as student_count' => fn ($e) => $e->where('status', 'active')])
            ->get();

        $examCounts = Exam::whereIn('class_id', $classes->pluck('id'))
            ->selectRaw('class_id, count(*) as c')->groupBy('class_id')->pluck('c', 'class_id');

        return view('v2.teacher.classes.index', compact('classes', 'examCounts'));
    }

    public function show(SchoolClass $class, ExamService $service)
    {
        $teacher = $this->teacher();
        abort_unless($teacher->classes()->where('v2_classes.id', $class->id)->exists(), 403);

        $class->load(['grade', 'subject']);

        $exams = Exam::where('class_id', $class->id)
            ->with('topic')
            ->withCount(['attempts as submitted_count' => fn ($q) => $q->where('status', 'submitted')])
            ->latest()->get();

        return view('v2.teacher.classes.show', [
            'class'        => $class,
            'studentCount' => StudentEnrollment::where('class_id', $class->id)->where('status', 'active')->count(),
            'exams'        => $exams,
            'topicStats'   => $service->classTopicStats($class),
            'matrix'       => $service->classStudentMatrix($class),
        ]);
    }

    /** One student's performance — only for students in this teacher's classes. */
    public function student(Student $student, ExamService $service)
    {
        $classIds = $this->teacher()->classes()->pluck('v2_classes.id');
        abort_unless(
            StudentEnrollment::where('student_id', $student->id)->whereIn('class_id', $classIds)->exists(),
            403
        );

        return view('v2.teacher.students.show', [
            'student' => $student,
            'stats'   => $service->studentStats($student),
        ]);
    }
}
