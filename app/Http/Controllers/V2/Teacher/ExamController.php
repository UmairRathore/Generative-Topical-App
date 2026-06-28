<?php

namespace App\Http\Controllers\V2\Teacher;

use App\Http\Controllers\Controller;
use App\Models\V2\Exam;
use App\Models\V2\QualityReview;
use App\Models\V2\Question;
use App\Models\V2\QuestionFlag;
use App\Models\V2\SchoolClass;
use App\Models\V2\Student;
use App\Models\V2\StudentEnrollment;
use App\Models\V2\Topic;
use App\Services\V2\AuditLogger;
use App\Services\V2\ExamService;
use App\Services\V2\NotificationService;
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
            ->withCount(['studentFlags as flagged_count' => fn ($q) => $q->where('level', 'student')->where('status', 'open')])
            ->withAvg(['attempts as avg_score' => fn ($q) => $q->where('status', 'submitted')], 'score')
            ->latest()
            ->get();

        return view('v2.teacher.exams.index', compact('exams'));
    }

    public function create()
    {
        $teacher = $this->teacher();
        $classes = $teacher->classes()->with(['subject', 'grade'])->where('is_active', true)->get();

        // Only topics that actually have a drawable question (matches drawRandomIds:
        // active + has options) - so a teacher can't pick a topic that yields nothing.
        $topicsBySubject = Topic::whereIn('subject_id', $classes->pluck('subject_id')->unique())
            ->whereHas('questions', fn ($q) => $q->active()->has('options'))
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

        // Gallery shows the full question (stem, diagrams, options) so it needs
        // options + images eager-loaded; the compact list view does not.
        $view = $request->input('view') === 'gallery' ? 'gallery' : 'list';

        $questions = $class
            ? Question::query()->active()->has('options')
                ->where('subject_id', $class->subject_id)
                ->when($topicIds, fn ($q, $t) => $q->whereIn('topic_id', $t))
                ->with($view === 'gallery'
                    ? ['topic:id,external_id,title', 'options', 'images']
                    : ['topic:id,external_id,title'])
                ->orderBy('topic_id')->orderByDesc('year')->orderBy('source_paper')->orderBy('question_number')
                ->paginate(24)->withQueryString()
            : null;

        $topics = $class
            ? Topic::where('subject_id', $class->subject_id)
                ->whereHas('questions', fn ($q) => $q->active()->has('options'))
                ->orderBy('sort_order')->get(['id', 'external_id', 'title'])
            : collect();

        return view('v2.teacher.exams.custom', compact('classes', 'class', 'classId', 'topics', 'topicIds', 'questions', 'view'));
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

    /**
     * Render the full gallery cards for a set of hand-picked question ids - used
     * by the "Selected questions" drawer so the teacher reviews the actual
     * questions (across every page/filter), not just a text snippet. Scoped to
     * the subjects the teacher teaches; selection order is preserved.
     */
    public function selectedCards(Request $request)
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $request->input('ids')))));
        if ($ids === []) {
            return response('');
        }

        return response($this->previewCardsHtml($ids, 'remove')['html']);
    }

    /**
     * RANDOM-preview: draw the questions the random generator would pick, so the
     * teacher can eyeball (and swap individual ones) before committing. Returns
     * { ids, html } so the page can track the working set client-side.
     */
    public function generatePreview(Request $request, ExamService $service)
    {
        $teacher = $this->teacher();
        $classIds = $teacher->classes()->pluck('v2_classes.id')->all();

        $data = $request->validate([
            'class_id'       => ['required', 'integer', Rule::in($classIds)],
            'topic_ids'      => ['nullable', 'array'],
            'topic_ids.*'    => ['integer'],
            'question_count' => ['required', 'integer', 'min:1', 'max:40'],
        ]);

        $class = SchoolClass::findOrFail($data['class_id']);
        $topicIds = $this->validTopicIds($data['topic_ids'] ?? [], $class->subject_id);

        $ids = $service->drawRandomIds($class->subject_id, $topicIds, (int) $data['question_count']);

        return response()->json($this->previewCardsHtml($ids, 'swap'));
    }

    /**
     * Replace one previewed question with another random draw from the same
     * topic pool (excluding everything already in the working set), and return
     * the refreshed { ids, html }.
     */
    public function swapPreview(Request $request, ExamService $service)
    {
        $teacher = $this->teacher();
        $classIds = $teacher->classes()->pluck('v2_classes.id')->all();

        $data = $request->validate([
            'class_id'    => ['required', 'integer', Rule::in($classIds)],
            'topic_ids'   => ['nullable', 'array'],
            'topic_ids.*' => ['integer'],
            'ids'         => ['required', 'array'],
            'ids.*'       => ['integer'],
            'replace'     => ['required', 'integer'],
        ]);

        $class = SchoolClass::findOrFail($data['class_id']);
        $topicIds = $this->validTopicIds($data['topic_ids'] ?? [], $class->subject_id);

        $current = array_map('intval', $data['ids']);
        $fresh = $service->drawRandomIds($class->subject_id, $topicIds, 1, $current);

        if ($fresh === []) {
            return response()->json(['error' => 'No other questions are available for these topics.'], 422);
        }

        $ids = array_map(fn ($id) => $id === (int) $data['replace'] ? $fresh[0] : $id, $current);

        return response()->json($this->previewCardsHtml($ids, 'swap'));
    }

    /**
     * Re-roll the preview: keep the questions the teacher ticked ($keep) in place
     * and draw fresh ones - different from everything currently shown - for the
     * rest. With nothing ticked this is a full new draw; tick 1 of 20 and only
     * the other 19 are replaced.
     */
    public function regeneratePreview(Request $request, ExamService $service)
    {
        $teacher = $this->teacher();
        $classIds = $teacher->classes()->pluck('v2_classes.id')->all();

        $data = $request->validate([
            'class_id'    => ['required', 'integer', Rule::in($classIds)],
            'topic_ids'   => ['nullable', 'array'],
            'topic_ids.*' => ['integer'],
            'ids'         => ['required', 'array'],
            'ids.*'       => ['integer'],
            'keep'        => ['nullable', 'array'],
            'keep.*'      => ['integer'],
        ]);

        $class = SchoolClass::findOrFail($data['class_id']);
        $topicIds = $this->validTopicIds($data['topic_ids'] ?? [], $class->subject_id);

        $current = array_map('intval', $data['ids']);
        $keep = array_values(array_intersect(array_map('intval', $data['keep'] ?? []), $current));
        $need = count($current) - count($keep);

        // Draw the replacements, excluding everything on screen so they're genuinely new.
        $fresh = $need > 0 ? $service->drawRandomIds($class->subject_id, $topicIds, $need, $current) : [];

        // Rebuild in place: kept slots stay, the rest take the next fresh draw.
        $i = 0;
        $result = [];
        foreach ($current as $id) {
            if (in_array($id, $keep, true)) {
                $result[] = $id;
            } elseif ($i < count($fresh)) {
                $result[] = $fresh[$i++];
            }
        }

        return response()->json($this->previewCardsHtml($result, 'swap'));
    }

    /**
     * Load + render the gallery cards for a set of question ids (scoped to the
     * teacher's subjects, order preserved). $action picks the per-card control:
     * 'remove' (custom selection drawer) or 'swap' (random preview).
     *
     * @param  array<int,int>  $ids
     * @return array{ids: array<int,int>, html: string}
     */
    private function previewCardsHtml(array $ids, string $action): array
    {
        $teacher = $this->teacher();
        $subjectIds = $teacher->classes()->pluck('v2_classes.subject_id')->unique()->all();

        $questions = Question::query()
            ->whereIn('id', $ids)
            ->whereIn('subject_id', $subjectIds)
            ->with(['topic:id,external_id,title', 'options', 'images', 'subject', 'paper'])
            ->get()
            ->keyBy('id');

        $ordered = collect($ids)->map(fn ($id) => $questions->get($id))->filter()->values();

        $html = view('v2.teacher.exams._selected_cards', [
            'questions' => $ordered,
            'action'    => $action,
        ])->render();

        return ['ids' => $ordered->pluck('id')->all(), 'html' => $html];
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

    /**
     * Release a draft test. The teacher only chooses WHEN it opens (now / +5 /
     * +10 / a scheduled time); the test then auto-closes `duration` minutes
     * after it opens. No manual expiry - the window IS the duration. Results
     * stay hidden until the test closes (released separately).
     */
    public function release(Exam $exam, Request $request, NotificationService $notifications)
    {
        $teacher = $this->teacher();
        abort_unless($exam->created_by === $teacher->id, 403);

        $data = $request->validate([
            'open'             => ['required', Rule::in(['now', 'in_5', 'in_10', 'schedule'])],
            // datetime-local is naive wall-clock; Carbon::parse reads it in the app tz.
            'release_at'       => ['nullable', 'required_if:open,schedule', 'date', 'after_or_equal:now'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:240'],
        ]);

        $duration = (int) $data['duration_minutes'];
        $from = match ($data['open']) {
            'in_5'     => now()->addMinutes(5),
            'in_10'    => now()->addMinutes(10),
            'schedule' => Carbon::parse($data['release_at']),
            default    => now(),
        };
        // Auto-close: the whole window is `duration` minutes from when it opens.
        $until = $from->copy()->addMinutes($duration);

        // Brand guard: a question flagged after this exam was frozen is no longer
        // 'active'. Hiding it only stops future generation - it still rides along
        // in this already-built paper. Block release so a flagged question can
        // never reach a student; the teacher regenerates or rebuilds the test.
        $flagged = $exam->examQuestions()
            ->whereHas('question', fn ($q) => $q->where('status', '!=', 'active'))
            ->count();
        if ($flagged > 0) {
            return back()->with('error', "This test contains {$flagged} question(s) that have been flagged and pulled from the pool. Regenerate or rebuild the test before releasing it.");
        }

        $exam->update([
            'status'           => 'released',
            'released_at'      => now(),
            'available_from'   => $from,
            'available_until'  => $until,
            'duration_minutes' => $duration,
        ]);

        AuditLogger::record('exam.released', $exam, [
            'from'  => $from->toDateTimeString(),
            'until' => $until->toDateTimeString(),
        ]);

        // Notify the class's students that a new (or scheduled) test is available.
        $notifications->announceExamRelease($exam);

        $msg = $exam->isScheduled()
            ? 'Test scheduled to open '.$from->diffForHumans().' - it closes '.$duration.' min after it opens.'
            : 'Test is live - students can take it now. It closes '.$until->diffForHumans().'.';

        return back()->with('success', $msg);
    }

    /** Release (or re-hide) results - the score + answer review - to students. */
    public function releaseResults(Exam $exam, Request $request, NotificationService $notifications)
    {
        $teacher = $this->teacher();
        abort_unless($exam->created_by === $teacher->id, 403);

        $release = $request->boolean('release', true);
        // Results can only be revealed once the test has CLOSED - never while it is
        // still open / in progress. (Re-hiding stays allowed any time.)
        abort_if($release && ! $exam->isExpired(), 422, 'You can release results once the test has closed.');

        $alreadyReleased = $exam->results_released_at !== null;
        $exam->update(['results_released_at' => $release ? ($exam->results_released_at ?? now()) : null]);

        // Notify students only on the first release (not on re-hide or re-release).
        if ($release && ! $alreadyReleased) {
            $notifications->announceResults($exam);
        }

        AuditLogger::record('exam.results_'.($release ? 'released' : 'hidden'), $exam);

        return back()->with('success', $release
            ? 'Results released - students can now see their scores and answers.'
            : 'Results hidden - students can no longer see their scores.');
    }

    public function show(Exam $exam, ExamService $service)
    {
        $teacher = $this->teacher();
        abort_unless(
            $exam->created_by === $teacher->id
                || $teacher->classes()->where('v2_classes.id', $exam->class_id)->exists(),
            403
        );

        $exam->load([
            'schoolClass.grade', 'schoolClass.subject', 'topic', 'creator',
            // Frozen questions (both custom + random modes write here) so the
            // teacher can preview the exact paper before releasing it. subject +
            // paper drive the per-question source label (paper code, level, session).
            'examQuestions.question.options', 'examQuestions.question.images',
            'examQuestions.question.subject', 'examQuestions.question.paper',
        ]);

        // Enrolled students - fetched without the pivot join so the Student
        // global scope's school_id filter stays unambiguous.
        $studentIds = StudentEnrollment::where('class_id', $exam->class_id)->pluck('student_id');
        $students   = Student::whereIn('id', $studentIds)->orderBy('name')->get();
        $attempts   = $exam->attempts()->get()->keyBy('student_id');
        $submitted = $attempts->where('status', 'submitted');

        // Every question in this exam that has a student report, OR was voided, OR
        // was escalated - each carrying the actions still available. Void and
        // escalate are INDEPENDENT, so a voided question can still be escalated and
        // vice-versa. Purely-dismissed questions (resolved, nothing pending) drop off.
        $allStudentFlags = QuestionFlag::studentLevel()
            ->where('exam_id', $exam->id)
            ->with('student:id,name,roll_number')->orderBy('created_at')
            ->get()->groupBy('question_id');

        $escalatedQids = QuestionFlag::teacherLevel()
            ->where('exam_id', $exam->id)->where('status', 'open')
            ->pluck('question_id')->map(fn ($id) => (int) $id)->all();

        $qids = collect($allStudentFlags->keys())
            ->merge($exam->examQuestions->where('is_voided', true)->pluck('question_id'))
            ->merge($escalatedQids)
            ->map(fn ($id) => (int) $id)->unique();

        $studentFlags = $qids->map(function ($qid) use ($exam, $allStudentFlags, $escalatedQids) {
            $eq        = $exam->examQuestions->firstWhere('question_id', $qid);
            $flags     = $allStudentFlags->get($qid) ?? collect();
            $openFlags = $flags->where('status', 'open');

            $isVoided    = (bool) $eq?->is_voided;
            $isEscalated = in_array($qid, $escalatedQids, true);
            $hasOpen     = $openFlags->isNotEmpty();

            if (! $hasOpen && ! $isVoided && ! $isEscalated) {
                return null; // resolved/dismissed only - nothing to show or do
            }

            return [
                'question_id'  => $qid,
                'sort_order'   => $eq?->sort_order,
                'is_voided'    => $isVoided,
                'is_escalated' => $isEscalated,
                'has_open'     => $hasOpen,
                'count'        => $flags->count(),
                'top_reason'   => ($openFlags->isNotEmpty() ? $openFlags : $flags)
                    ->groupBy('reason')->sortByDesc->count()->keys()->first(),
                'flags'        => $flags->map(fn ($f) => [
                    'student' => $f->student?->name,
                    'roll'    => $f->student?->roll_number,
                    'reason'  => $f->reasonLabel(),
                    'note'    => $f->note,
                    'shot'    => $f->screenshot_path,
                    'when'    => $f->created_at,
                ])->all(),
            ];
        })->filter()->sortByDesc('count')->values();

        // Phase 4.1: surface the Support quality-review outcome (read-only) on each
        // reported question - the teacher sees the decision, not just "Voided".
        $qrReviews = QualityReview::whereIn('question_id', $studentFlags->pluck('question_id'))
            ->where('status', 'decided')
            ->orderByDesc('reviewed_at')->orderByDesc('id')
            ->get()->groupBy('question_id')->map->first();
        $studentFlags = $studentFlags->map(function ($f) use ($qrReviews) {
            $rev = $qrReviews->get($f['question_id']);
            $f['qr_outcome'] = $rev?->outcome;
            $f['qr_propagation'] = ($rev && $rev->outcome === 'material')
                ? ($rev->propagation_status === 'propagated' ? 'completed' : 'pending')
                : null;

            return $f;
        })->values();

        return view('v2.teacher.exams.show', [
            'exam'       => $exam,
            'students'   => $students,
            'attempts'   => $attempts,
            'topicStats' => $service->topicStatsForExam($exam),
            'avg'        => $submitted->count() ? (int) round($submitted->avg(fn ($a) => $a->percentage)) : null,
            'submittedCount' => $submitted->count(),
            'studentFlags'    => $studentFlags,
        ]);
    }

    /** Dismiss the open student reports on a question (the question stays as-is). */
    public function dismissFlags(Exam $exam, Question $question)
    {
        $teacher = $this->teacher();
        $this->assertOwnsExam($exam, $teacher);

        QuestionFlag::studentLevel()
            ->where('exam_id', $exam->id)->where('question_id', $question->id)->where('status', 'open')
            ->update(['status' => 'dismissed', 'resolved_by' => $teacher->id, 'resolved_at' => now()]);

        return back()->with('success', 'Reports dismissed - the question stays as it is.');
    }

    /**
     * Send a flagged question for quality review - the single "questionable"
     * action. It atomically (1) VOIDS the question for THIS exam (students are
     * never graded on it; marks/analytics recompute via the pivot), (2) locks the
     * student reports as "escalated", (3) creates the Support Team queue item, and
     * (4) pulls the bank question to under_review so it's hidden from new exams.
     * Student answers and historical records are preserved; the exam-level void is
     * permanent regardless of the later QA outcome.
     */
    public function sendForReview(Exam $exam, Question $question, Request $request, ExamService $service, NotificationService $notifications)
    {
        $teacher = $this->teacher();
        $this->assertOwnsExam($exam, $teacher);
        abort_unless($exam->examQuestions()->where('question_id', $question->id)->exists(), 404);

        $note = trim((string) $request->input('note')) ?: null;

        $topReason = QuestionFlag::studentLevel()
            ->where('exam_id', $exam->id)->where('question_id', $question->id)->where('status', 'open')
            ->selectRaw('reason, count(*) c')->groupBy('reason')->orderByDesc('c')->value('reason') ?? 'wrong_answer';

        // 1. Void for this exam + recompute marksheets; notify any student whose visible score changed.
        $changed = $service->voidExamQuestion($exam, $question->id, $teacher->id, $topReason);
        if ($changed) {
            $notifications->announceScoreAdjusted($exam, $changed);
        }

        // 2. Lock the student reports on this question as escalated (no re-report).
        QuestionFlag::studentLevel()
            ->where('exam_id', $exam->id)->where('question_id', $question->id)->where('status', 'open')
            ->update(['status' => 'escalated']);

        // 3. A teacher-level flag with status 'open' = an item in the Support Team review queue.
        QuestionFlag::updateOrCreate(
            ['level' => 'teacher', 'exam_id' => $exam->id, 'question_id' => $question->id, 'status' => 'open'],
            ['school_id' => $exam->school_id, 'flagged_by_teacher_id' => $teacher->id, 'reason' => $topReason, 'note' => $note],
        );

        // 4. Pull the bank question from the pool until review completes.
        if ($question->status === 'active') {
            $question->update(['status' => 'under_review']);
        }

        // 5. Ensure one open Support quality review and attach this exam's reports
        //    (the teacher flag above + the just-escalated student flags) to it.
        QualityReview::openFor($question->id);

        AuditLogger::record('exam.question_sent_for_review', $exam, ['question_id' => $question->id, 'students_adjusted' => count($changed)]);

        return back()->with('success', 'Sent for quality review - voided for this exam and hidden from new exams pending review.');
    }

    private function assertOwnsExam(Exam $exam, $teacher): void
    {
        abort_unless(
            $exam->created_by === $teacher->id
                || $teacher->classes()->where('v2_classes.id', $exam->class_id)->exists(),
            403
        );
    }

    /** One student's full submitted paper - the same per-question review the student sees. */
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

        $exam->load(['examQuestions.questionVersion', 'examQuestions.question.options', 'examQuestions.question.images', 'topic', 'schoolClass']);
        $exam->renderFrozenQuestions(); // show exactly what the student saw
        $answers = $attempt->answers()->get()->keyBy('question_id');
        $attempt->setRelation('answers', $answers);

        $result = $service->resultBreakdown($exam, $attempt);

        return view('v2.teacher.exams.student_paper', [
            'exam'          => $exam,
            'student'       => $student,
            'attempt'       => $attempt,
            'answers'       => $answers,
            'topicStats'    => $service->topicStatsForAttempt($attempt),
            'breakdown'     => $result['breakdown'],
            'palette'       => $result['palette'],
            'revealCorrect' => true,
        ]);
    }
}
