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
            ? Topic::where('subject_id', $class->subject_id)->orderBy('sort_order')->get(['id', 'external_id', 'title'])
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
     * Render the full gallery cards for a set of hand-picked question ids — used
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
     * and draw fresh ones — different from everything currently shown — for the
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

        // Brand guard: a question flagged after this exam was frozen is no longer
        // 'active'. Hiding it only stops future generation — it still rides along
        // in this already-built paper. Block release so a flagged question can
        // never reach a student; the teacher regenerates or rebuilds the test.
        $flagged = $exam->examQuestions()
            ->whereHas('question', fn ($q) => $q->where('status', '!=', 'active'))
            ->count();
        if ($flagged > 0) {
            return back()->with('error', "This test contains {$flagged} question(s) that have been flagged and pulled from the pool. Regenerate or rebuild the test before releasing it.");
        }

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

        $exam->load([
            'schoolClass.grade', 'schoolClass.subject', 'topic', 'creator',
            // Frozen questions (both custom + random modes write here) so the
            // teacher can preview the exact paper before releasing it. subject +
            // paper drive the per-question source label (paper code, level, session).
            'examQuestions.question.options', 'examQuestions.question.images',
            'examQuestions.question.subject', 'examQuestions.question.paper',
        ]);

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
