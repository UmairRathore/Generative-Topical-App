<?php

namespace App\Http\Controllers\V2\BranchAdmin;

use App\Models\V2\Exam;
use App\Models\V2\Grade;
use App\Models\V2\Report;
use App\Models\V2\SchoolClass;
use App\Models\V2\Student;
use App\Models\V2\StudentEnrollment;
use App\Models\V2\Subject;
use App\Models\V2\Teacher;
use App\Services\V2\ExamService;

/*
|--------------------------------------------------------------------------
| Branch Admin - Analytics
|--------------------------------------------------------------------------
| Same drill-down as the school admin (grade → teacher → subject → topic →
| class → student → paper), but every stat is narrowed to the admin's branch
| (school_id + branch_id). Models are also branch-scoped by the global scope.
*/
class AnalyticsController extends BaseController
{
    public function index(ExamService $service)
    {
        $sid = $this->schoolId();
        $bid = $this->branchId();

        return view('v2.branch_admin.analytics.index', [
            'branch'      => $this->branch(),
            'overview'    => $service->schoolOverview($sid, $bid),
            'grades'      => $service->gradeWideRows($sid, $bid),
            'subjects'    => $service->subjectWideRows($sid, $bid),
            'topicGroups' => $service->schoolTopicStatsBySubject($sid, $bid),
            'teachers'    => $service->schoolTeacherRows($sid, $bid),
            'classes'     => $service->schoolClassRows($sid, $bid),
        ]);
    }

    public function grades(ExamService $service)
    {
        return view('v2.branch_admin.analytics.grades', [
            'rows' => $service->gradeWideRows($this->schoolId(), $this->branchId()),
        ]);
    }

    public function grade(Grade $grade, ExamService $service)
    {
        abort_unless($grade->school_id === $this->schoolId(), 403);
        $sid = $this->schoolId();
        $bid = $this->branchId();

        return view('v2.branch_admin.analytics.grade', [
            'grade'      => $grade,
            'summary'    => collect($service->gradeWideRows($sid, $bid))->firstWhere('id', $grade->id),
            'topicStats' => $service->gradeTopicStats($sid, $grade->id, $bid),
            'classes'    => collect($service->schoolClassRows($sid, $bid))->where('grade', $grade->name)->values()->all(),
        ]);
    }

    public function subjects(ExamService $service)
    {
        return view('v2.branch_admin.analytics.subjects', [
            'rows' => $service->subjectWideRows($this->schoolId(), $this->branchId()),
        ]);
    }

    public function subject(Subject $subject, ExamService $service)
    {
        $sid = $this->schoolId();
        $bid = $this->branchId();

        return view('v2.branch_admin.analytics.subject', [
            'subject'    => $subject,
            'summary'    => collect($service->subjectWideRows($sid, $bid))->firstWhere('id', $subject->id),
            'topicStats' => $service->subjectTopicStats($sid, $subject->id, $bid),
            'classes'    => collect($service->schoolClassRows($sid, $bid))->where('subject', $subject->name)->values()->all(),
        ]);
    }

    public function topics(ExamService $service)
    {
        $sid = $this->schoolId();
        $bid = $this->branchId();

        return view('v2.branch_admin.analytics.topics', [
            'topicStats' => $service->schoolTopicStats($sid, $bid),
            'split'      => $service->examKindSplit($sid, null, null, $bid),
        ]);
    }

    public function teacher(Teacher $teacher, ExamService $service)
    {
        abort_unless($teacher->branch_id === $this->branchId(), 403);

        $classes = $teacher->classes()->with(['grade', 'subject'])
            ->withCount(['enrollments as student_count' => fn ($e) => $e->where('status', 'active')])
            ->get();

        $examCounts = Exam::where('created_by', $teacher->id)
            ->selectRaw('class_id, count(*) c')->groupBy('class_id')->pluck('c', 'class_id');

        return view('v2.branch_admin.analytics.teacher', [
            'teacher'    => $teacher,
            'topicStats' => $service->teacherTopicStats($teacher->id),
            'classes'    => $classes,
            'examCounts' => $examCounts,
        ]);
    }

    public function classDetail(SchoolClass $class, ExamService $service)
    {
        abort_unless($class->branch_id === $this->branchId(), 403);
        $class->load(['grade', 'subject']);

        return view('v2.branch_admin.analytics.class', [
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
        abort_unless($student->branch_id === $this->branchId(), 403);

        return view('v2.branch_admin.analytics.student', [
            'student' => $student,
            'stats'   => $service->studentStats($student),
            'reports' => Report::where('student_id', $student->id)->latest()->limit(12)->get(),
        ]);
    }

    public function studentPaper(Exam $exam, Student $student, ExamService $service)
    {
        abort_unless($student->branch_id === $this->branchId(), 403);
        abort_unless($exam->school_id === $this->schoolId(), 403);
        abort_unless(
            StudentEnrollment::where('class_id', $exam->class_id)->where('student_id', $student->id)->exists(),
            403
        );

        $attempt = $exam->attempts()->where('student_id', $student->id)
            ->where('status', 'submitted')->firstOrFail();

        $exam->load(['examQuestions.question.options', 'examQuestions.question.images', 'topic', 'schoolClass']);

        return view('v2.branch_admin.analytics.paper', [
            'exam'       => $exam,
            'student'    => $student,
            'attempt'    => $attempt,
            'answers'    => $attempt->answers()->get()->keyBy('question_id'),
            'topicStats' => $service->topicStatsForAttempt($attempt),
        ]);
    }
}
