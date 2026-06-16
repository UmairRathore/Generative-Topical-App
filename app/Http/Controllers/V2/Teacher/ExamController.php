<?php

namespace App\Http\Controllers\V2\Teacher;

use App\Http\Controllers\Controller;
use App\Models\V2\Exam;
use App\Models\V2\SchoolClass;
use App\Models\V2\Student;
use App\Models\V2\StudentEnrollment;
use App\Models\V2\Topic;
use App\Services\V2\AuditLogger;
use App\Services\V2\ExamService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExamController extends Controller
{
    private function teacher()
    {
        return auth('v2_teacher')->user();
    }

    public function index()
    {
        $exams = Exam::where('created_by', $this->teacher()->id)
            ->with([
                'schoolClass' => fn ($q) => $q->with(['grade', 'subject'])
                    ->withCount(['enrollments as student_count' => fn ($e) => $e->where('status', 'active')]),
                'topic',
            ])
            ->withCount(['attempts as submitted_count' => fn ($q) => $q->where('status', 'submitted')])
            ->withAvg(['attempts as avg_score' => fn ($q) => $q->where('status', 'submitted')], 'score')
            ->latest()
            ->get();

        return view('v2.teacher.exams.index', compact('exams'));
    }

    public function create()
    {
        $teacher = $this->teacher();
        $classes = $teacher->classes()->with(['subject', 'grade'])->where('is_active', true)->get();

        $topicsBySubject = Topic::whereIn('subject_id', $classes->pluck('subject_id')->unique())
            ->orderBy('sort_order')
            ->get(['id', 'external_id', 'title', 'subject_id'])
            ->groupBy('subject_id');

        // Compact map the form's Alpine uses to swap topics when a class is picked.
        $classMeta = $classes->mapWithKeys(fn ($c) => [$c->id => [
            'subject' => $c->subject?->name,
            'topics'  => ($topicsBySubject[$c->subject_id] ?? collect())
                ->map(fn ($t) => ['id' => $t->id, 'label' => $t->external_id.'. '.$t->title])
                ->values(),
        ]]);

        return view('v2.teacher.exams.create', compact('classes', 'classMeta'));
    }

    public function store(Request $request, ExamService $service)
    {
        $teacher = $this->teacher();
        $classIds = $teacher->classes()->pluck('v2_classes.id')->all();

        $data = $request->validate([
            'class_id'         => ['required', 'integer', Rule::in($classIds)],
            'topic_id'         => ['nullable', 'integer'],
            'question_count'   => ['required', 'integer', 'min:1', 'max:40'],
            'title'            => ['required', 'string', 'max:120'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
        ]);

        $class = SchoolClass::findOrFail($data['class_id']);

        // Topic (if any) must belong to the class's subject — no out-of-scope topics.
        if (! empty($data['topic_id'])) {
            abort_unless(
                Topic::where('id', $data['topic_id'])->where('subject_id', $class->subject_id)->exists(),
                422,
                'Selected topic does not belong to this class subject.'
            );
        } else {
            $data['topic_id'] = null;
        }

        $exam = $service->generate($teacher, $data);
        AuditLogger::record('exam.created', $exam, ['class_id' => $class->id, 'count' => $exam->question_count]);

        return redirect()
            ->route('v2.teacher.exams.show', $exam)
            ->with('success', "Test generated with {$exam->question_count} questions and assigned to {$class->name}.");
    }

    public function show(Exam $exam, ExamService $service)
    {
        $teacher = $this->teacher();
        abort_unless(
            $exam->created_by === $teacher->id
                || $teacher->classes()->where('v2_classes.id', $exam->class_id)->exists(),
            403
        );

        $exam->load(['schoolClass.grade', 'schoolClass.subject', 'topic', 'creator']);

        // Enrolled students — fetched without the pivot join so the Student
        // global scope's school_id filter stays unambiguous.
        $studentIds = StudentEnrollment::where('class_id', $exam->class_id)->pluck('student_id');
        $students   = Student::whereIn('id', $studentIds)->orderBy('name')->get();
        $attempts   = $exam->attempts()->get()->keyBy('student_id');
        $submitted = $attempts->where('status', 'submitted');

        return view('v2.teacher.exams.show', [
            'exam'       => $exam,
            'students'   => $students,
            'attempts'   => $attempts,
            'topicStats' => $service->topicStatsForExam($exam),
            'avg'        => $submitted->count() ? (int) round($submitted->avg(fn ($a) => $a->percentage)) : null,
            'submittedCount' => $submitted->count(),
        ]);
    }

    /** One student's full submitted paper — the same per-question review the student sees. */
    public function studentPaper(Exam $exam, Student $student, ExamService $service)
    {
        $teacher = $this->teacher();
        abort_unless(
            $exam->created_by === $teacher->id
                || $teacher->classes()->where('v2_classes.id', $exam->class_id)->exists(),
            403
        );

        // The student must be enrolled in this exam's class (keeps it scoped to the teacher's class).
        abort_unless(
            StudentEnrollment::where('class_id', $exam->class_id)->where('student_id', $student->id)->exists(),
            403
        );

        $attempt = $exam->attempts()
            ->where('student_id', $student->id)
            ->where('status', 'submitted')
            ->firstOrFail();

        $exam->load(['examQuestions.question.options', 'examQuestions.question.images', 'topic', 'schoolClass']);
        $answers = $attempt->answers()->get()->keyBy('question_id');

        return view('v2.teacher.exams.student_paper', [
            'exam'       => $exam,
            'student'    => $student,
            'attempt'    => $attempt,
            'answers'    => $answers,
            'topicStats' => $service->topicStatsForAttempt($attempt),
        ]);
    }
}
