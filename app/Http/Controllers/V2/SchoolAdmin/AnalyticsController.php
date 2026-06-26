<?php

namespace App\Http\Controllers\V2\SchoolAdmin;

use App\Models\V2\Exam;
use App\Models\V2\Grade;
use App\Models\V2\SchoolClass;
use App\Models\V2\Student;
use App\Models\V2\Subject;
use App\Models\V2\StudentEnrollment;
use App\Models\V2\Teacher;
use App\Services\V2\ExamService;

/*
|--------------------------------------------------------------------------
| School Admin - Analytics
|--------------------------------------------------------------------------
| Whole-school analytics with drill-down: School → Teacher → Class → Student.
| Every query is scoped to the admin's school (global scopes + explicit checks).
*/
class AnalyticsController extends BaseController
{
    public function index(ExamService $service)
    {
        $sid = $this->schoolId();

        return view('v2.school_admin.analytics.index', [
            'overview'   => $service->schoolOverview($sid),
            'topicStats' => $service->schoolTopicStats($sid),
            'teachers'   => $service->schoolTeacherRows($sid),
            'classes'    => $service->schoolClassRows($sid),
        ]);
    }

    public function teacher(Teacher $teacher, ExamService $service)
    {
        abort_unless($teacher->school_id === $this->schoolId(), 403);

        $classes = $teacher->classes()->with(['grade', 'subject'])
            ->withCount(['enrollments as student_count' => fn ($e) => $e->where('status', 'active')])
            ->get();

        $examCounts = Exam::where('created_by', $teacher->id)
            ->selectRaw('class_id, count(*) c')->groupBy('class_id')->pluck('c', 'class_id');

        return view('v2.school_admin.analytics.teacher', [
            'teacher'    => $teacher,
            'topicStats' => $service->teacherTopicStats($teacher->id),
            'classes'    => $classes,
            'examCounts' => $examCounts,
        ]);
    }

    public function classDetail(SchoolClass $class, ExamService $service)
    {
        abort_unless($class->school_id === $this->schoolId(), 403);
        $class->load(['grade', 'subject']);

        return view('v2.school_admin.analytics.class', [
            'class'        => $class,
            'studentCount' => StudentEnrollment::where('class_id', $class->id)->where('status', 'active')->count(),
            'exams'        => Exam::where('class_id', $class->id)->with('topic')
                ->withCount(['attempts as submitted_count' => fn ($q) => $q->where('status', 'submitted')])
                ->latest()->get(),
            'topicStats'   => $service->classTopicStats($class),
            'matrix'       => $service->classStudentMatrix($class),
        ]);
    }

    public function student(Student $student, ExamService $service)
    {
        abort_unless($student->school_id === $this->schoolId(), 403);

        return view('v2.school_admin.analytics.student', [
            'student' => $student,
            'stats'   => $service->studentStats($student),
        ]);
    }

    /* ---- Grade drill-down ------------------------------------------------ */

    public function grades(ExamService $service)
    {
        return view('v2.school_admin.analytics.grades', [
            'rows' => $service->gradeWideRows($this->schoolId()),
        ]);
    }

    public function grade(Grade $grade, ExamService $service)
    {
        abort_unless($grade->school_id === $this->schoolId(), 403);
        $sid = $this->schoolId();

        return view('v2.school_admin.analytics.grade', [
            'grade'      => $grade,
            'summary'    => collect($service->gradeWideRows($sid))->firstWhere('id', $grade->id),
            'topicStats' => $service->gradeTopicStats($sid, $grade->id),
            'classes'    => collect($service->schoolClassRows($sid))->where('grade', $grade->name)->values()->all(),
        ]);
    }

    /* ---- Subject drill-down ---------------------------------------------- */

    public function subjects(ExamService $service)
    {
        return view('v2.school_admin.analytics.subjects', [
            'rows' => $service->subjectWideRows($this->schoolId()),
        ]);
    }

    public function subject(Subject $subject, ExamService $service)
    {
        $sid = $this->schoolId();

        return view('v2.school_admin.analytics.subject', [
            'subject'    => $subject,
            'summary'    => collect($service->subjectWideRows($sid))->firstWhere('id', $subject->id),
            'topicStats' => $service->subjectTopicStats($sid, $subject->id),
            'classes'    => collect($service->schoolClassRows($sid))->where('subject', $subject->name)->values()->all(),
        ]);
    }

    /* ---- Topic view (single / mixed split) ------------------------------- */

    public function topics(ExamService $service)
    {
        $sid = $this->schoolId();

        return view('v2.school_admin.analytics.topics', [
            'topicStats' => $service->schoolTopicStats($sid),
            'split'      => $service->examKindSplit($sid),
        ]);
    }

    /* ---- Individual graded paper ----------------------------------------- */

    public function studentPaper(Exam $exam, Student $student, ExamService $service)
    {
        $sid = $this->schoolId();
        abort_unless($exam->school_id === $sid, 403);
        abort_unless($student->school_id === $sid, 403);
        abort_unless(
            StudentEnrollment::where('class_id', $exam->class_id)->where('student_id', $student->id)->exists(),
            403
        );

        $attempt = $exam->attempts()->where('student_id', $student->id)
            ->where('status', 'submitted')->firstOrFail();

        $exam->load(['examQuestions.question.options', 'examQuestions.question.images', 'topic', 'schoolClass']);

        return view('v2.school_admin.analytics.paper', [
            'exam'       => $exam,
            'student'    => $student,
            'attempt'    => $attempt,
            'answers'    => $attempt->answers()->get()->keyBy('question_id'),
            'topicStats' => $service->topicStatsForAttempt($attempt),
        ]);
    }
}
