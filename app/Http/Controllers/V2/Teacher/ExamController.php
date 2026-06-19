<?php

namespace App\Http\Controllers\V2\Teacher;

use App\Http\Controllers\Controller;
use App\Models\V2\Exam;
use App\Models\V2\Question;
use App\Models\V2\SchoolClass;
use App\Models\V2\Student;
use App\Models\V2\StudentEnrollment;
use App\Models\V2\Topic;
use App\Services\V2\AuditLogger;
use App\Services\V2\ExamService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'topic_ids'        => ['nullable', 'array'],
            'topic_ids.*'      => ['integer'],
            'question_count'   => ['required', 'integer', 'min:1', 'max:40'],
            'title'            => ['required', 'string', 'max:120'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
        ]);

        $class = SchoolClass::findOrFail($data['class_id']);

        // Any chosen topics must belong to the class's subject - no out-of-scope topics.
        $data['topic_ids'] = $this->validTopicIds($data['topic_ids'] ?? [], $class->subject_id);

        $exam = $service->generate($teacher, $data);
        AuditLogger::record('exam.created', $exam, ['class_id' => $class->id, 'count' => $exam->question_count]);

        return redirect()
            ->route('v2.teacher.exams.show', $exam)
            ->with('success', "Test generated with {$exam->question_count} questions and assigned to {$class->name}.");
    }

    /** CUSTOM mode: browse + hand-pick questions for a class. */
    public function custom(Request $request)
    {
        $teacher = $this->teacher();
        $classes = $teacher->classes()->with(['subject', 'grade'])->where('is_active', true)->get();

        $classId = $request->integer('class_id') ?: ($classes->first()->id ?? null);
        $class = $classes->firstWhere('id', $classId);

        $topicIds = $this->validTopicIds((array) $request->input('topic_ids', []), $class?->subject_id);

        $questions = $class
            ? Question::query()->active()->has('options')
                ->where('subject_id', $class->subject_id)
                ->when($topicIds, fn ($q, $t) => $q->whereIn('topic_id', $t))
                ->with('topic:id,external_id,title')
                ->orderBy('topic_id')->orderByDesc('year')->orderBy('source_paper')->orderBy('question_number')
                ->paginate(24)->withQueryString()
            : null;

        $topics = $class
            ? Topic::where('subject_id', $class->subject_id)->orderBy('sort_order')->get(['id', 'external_id', 'title'])
            : collect();

        return view('v2.teacher.exams.custom', compact('classes', 'class', 'classId', 'topics', 'topicIds', 'questions'));
    }

    public function storeCustom(Request $request, ExamService $service)
    {
        $teacher = $this->teacher();
        $classIds = $teacher->classes()->pluck('v2_classes.id')->all();

        $data = $request->validate([
            'class_id'         => ['required', 'integer', Rule::in($classIds)],
            'title'            => ['required', 'string', 'max:120'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'question_ids'     => ['required', 'string'],
        ]);

        $ids = array_values(array_filter(array_map('intval', explode(',', $data['question_ids']))));
        abort_if($ids === [], 422, 'Select at least one question.');

        $exam = $service->createFromQuestions($teacher, $data, $ids);
        AuditLogger::record('exam.created_custom', $exam, ['count' => $exam->question_count]);

        return redirect()
            ->route('v2.teacher.exams.show', $exam)
            ->with('success', "Test created with {$exam->question_count} hand-picked questions.");
    }

    /** Keep only topic ids that belong to the subject. */
    private function validTopicIds(array $ids, ?int $subjectId): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === [] || $subjectId === null) {
            return [];
        }

        return Topic::where('subject_id', $subjectId)->whereIn('id', $ids)->pluck('id')->all();
    }

    /** Release a draft test — immediately or at a scheduled time, with optional expiry. */
    public function release(Exam $exam, Request $request)
    {
        $teacher = $this->teacher();
        abort_unless($exam->created_by === $teacher->id, 403);

        $data = $request->validate([
            'mode'            => ['required', Rule::in(['now', 'schedule'])],
            'release_at'      => ['nullable', 'required_if:mode,schedule', 'date'],
            'expires_at'      => ['nullable', 'date'],
            'release_results' => ['nullable', 'boolean'],
        ]);

        $from  = $data['mode'] === 'schedule' ? Carbon::parse($data['release_at']) : now();
        $until = ! empty($data['expires_at']) ? Carbon::parse($data['expires_at']) : null;

        abort_if($until && $until->lessThanOrEqualTo($from), 422, 'Expiry must be after the release time.');

        $exam->update([
            'status'              => 'released',
            'released_at'         => now(),
            'available_from'      => $from,
            'available_until'     => $until,
            // Optionally make results visible the moment students submit.
            'results_released_at' => $request->boolean('release_results') ? now() : $exam->results_released_at,
        ]);

        AuditLogger::record('exam.released', $exam, [
            'from'    => $from->toDateTimeString(),
            'until'   => $until?->toDateTimeString(),
            'results' => $request->boolean('release_results'),
        ]);

        $msg = $exam->isScheduled() ? 'Test scheduled to open '.$from->diffForHumans().'.' : 'Test released — students can take it now.';
        if ($request->boolean('release_results')) {
            $msg .= ' Results will be visible to students as they submit.';
        }

        return back()->with('success', $msg);
    }

    /** Release (or re-hide) results — the score + answer review — to students. */
    public function releaseResults(Exam $exam, Request $request)
    {
        $teacher = $this->teacher();
        abort_unless($exam->created_by === $teacher->id, 403);

        $release = $request->boolean('release', true);
        $exam->update(['results_released_at' => $release ? ($exam->results_released_at ?? now()) : null]);

        AuditLogger::record('exam.results_'.($release ? 'released' : 'hidden'), $exam);

        return back()->with('success', $release
            ? 'Results released — students can now see their scores and answers.'
            : 'Results hidden — students can no longer see their scores.');
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
