<?php

namespace App\Services\V2;

use App\Models\V2\Exam;
use App\Models\V2\ExamAttempt;
use App\Models\V2\ExamQuestion;
use App\Models\V2\Question;
use App\Models\V2\QuestionFlag;
use App\Models\V2\SchoolClass;
use App\Models\V2\Student;
use App\Models\V2\StudentEnrollment;
use App\Models\V2\Teacher;
use App\Models\V2\Topic;
use Illuminate\Support\Facades\DB;

class ExamService
{
    /**
     * Generate an exam: randomly pick questions from the bank scoped to the
     * class's subject and the chosen topic, then freeze them into the exam.
     *
     * Answerable questions (those with a known correct_answer) are preferred so
     * auto-marking works; if not enough are answerable yet, the rest are filled
     * from the topic so the exam is still complete.
     *
     * @param array{class_id:int,topic_id:?int,question_count:int,title:string,duration_minutes:?int,year_from:?int,year_to:?int} $data
     */
    /** RANDOM mode: draw $count questions from the class subject across one or more topics. */
    public function generate(Teacher $teacher, array $data): Exam
    {
        /** @var SchoolClass $class */
        $class = SchoolClass::findOrFail($data['class_id']);
        $count = max(1, min(40, (int) $data['question_count']));

        // Accept either topic_ids[] (new, multi) or a single topic_id (legacy).
        $topicIds = array_values(array_filter(array_map('intval',
            (array) ($data['topic_ids'] ?? (! empty($data['topic_id']) ? [$data['topic_id']] : [])))));

        $ids = $this->drawRandomIds(
            $class->subject_id,
            $topicIds,
            $count,
            [],
            $data['year_from'] ?? null,
            $data['year_to'] ?? null,
        );

        shuffle($ids);

        return $this->freeze($teacher, $class, $data, $ids, count($topicIds) === 1 ? $topicIds[0] : null);
    }

    /**
     * Draw up to $count random question ids from a subject across the given
     * topics, excluding $exclude. Answerable questions (known correct_answer) are
     * preferred so auto-marking works; the rest are filled from the same pool.
     * Used by both one-shot generation and the live preview / per-question swap.
     *
     * @param  array<int,int>  $topicIds
     * @param  array<int,int>  $exclude
     * @return array<int,int>
     */
    public function drawRandomIds(int $subjectId, array $topicIds, int $count, array $exclude = [], ?int $yearFrom = null, ?int $yearTo = null): array
    {
        $base = Question::query()
            ->active() // draft / archived questions are never drawn into a test
            ->has('options') // never draw a question with no answer choices (incomplete source data)
            ->where('subject_id', $subjectId)
            ->when($topicIds, fn ($q, $t) => $q->whereIn('topic_id', $t))
            ->when($exclude, fn ($q, $e) => $q->whereNotIn('id', $e))
            ->when($yearFrom, fn ($q, $y) => $q->where('year', '>=', $y))
            ->when($yearTo, fn ($q, $y) => $q->where('year', '<=', $y));

        // Prefer answerable questions; fill the remainder if there aren't enough.
        $ids = (clone $base)->whereNotNull('correct_answer')
            ->inRandomOrder()->limit($count)->pluck('id')->all();

        if (count($ids) < $count) {
            $fill = (clone $base)->whereNull('correct_answer')
                ->whereNotIn('id', $ids)
                ->inRandomOrder()->limit($count - count($ids))->pluck('id')->all();
            $ids = array_merge($ids, $fill);
        }

        return $ids;
    }

    /**
     * CUSTOM mode: build an exam from the teacher's hand-picked question ids.
     * Only ids that are active, have options, and belong to the class subject are
     * kept; selection is capped at 40 and presented in a shuffled order.
     */
    public function createFromQuestions(Teacher $teacher, array $data, array $questionIds): Exam
    {
        /** @var SchoolClass $class */
        $class = SchoolClass::findOrFail($data['class_id']);

        $valid = Question::query()->active()->has('options')
            ->where('subject_id', $class->subject_id)
            ->whereIn('id', $questionIds)
            ->pluck('id')->all();

        // Preserve the teacher's selection, drop invalid ids, cap at 40, then shuffle.
        $ids = array_values(array_intersect(array_map('intval', $questionIds), $valid));
        $ids = array_slice($ids, 0, 40);
        abort_if($ids === [], 422, 'Select at least one valid question.');
        shuffle($ids);

        $topicIds = Question::whereIn('id', $ids)->distinct()->pluck('topic_id')->filter();

        return $this->freeze($teacher, $class, $data, $ids, $topicIds->count() === 1 ? (int) $topicIds->first() : null);
    }

    /** Create the draft exam + freeze its questions (shared by both modes). */
    private function freeze(Teacher $teacher, SchoolClass $class, array $data, array $ids, ?int $topicId): Exam
    {
        return DB::transaction(function () use ($teacher, $class, $data, $ids, $topicId) {
            $exam = Exam::create([
                'school_id'        => $teacher->school_id,
                'class_id'         => $class->id,
                'subject_id'       => $class->subject_id,
                'topic_id'         => $topicId,
                'created_by'       => $teacher->id,
                'title'            => $data['title'],
                'question_count'   => count($ids),
                'total_marks'      => count($ids),
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'year_from'        => $data['year_from'] ?? null,
                'year_to'          => $data['year_to'] ?? null,
                // Created as a draft - not visible to students until the teacher
                // releases it (now or scheduled) with an optional expiry.
                'status'           => 'draft',
                'published_at'     => now(),
            ]);

            // Freeze the EXACT current content version of each question so the exam
            // always renders what the student saw, even after later corrections.
            $versionMap = DB::table('v2_questions')->whereIn('id', $ids)->pluck('current_version_id', 'id');

            $rows = [];
            foreach ($ids as $i => $qid) {
                $rows[] = [
                    'exam_id'             => $exam->id,
                    'question_id'         => $qid,
                    'question_version_id' => $versionMap[$qid] ?? null,
                    'sort_order'          => $i + 1,
                    'marks'               => 1,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];
            }
            if ($rows) {
                DB::table('v2_exam_questions')->insert($rows);
            }

            return $exam;
        });
    }

    /** Get (or open) a student's attempt for an exam. */
    public function startAttempt(Exam $exam, Student $student): ExamAttempt
    {
        return ExamAttempt::firstOrCreate(
            ['exam_id' => $exam->id, 'student_id' => $student->id],
            [
                'school_id'       => $student->school_id,
                'status'          => 'in_progress',
                'total_questions' => $exam->question_count,
                'started_at'      => now(),
            ]
        );
    }

    /**
     * Auto-mark a submitted attempt. $responses maps question_id => 'A'|'B'|'C'|'D'.
     */
    public function submit(ExamAttempt $attempt, array $responses): ExamAttempt
    {
        $exam = $attempt->exam()
            ->with(['examQuestions.question:id,correct_answer', 'examQuestions.questionVersion:id,correct_answer'])
            ->firstOrFail();

        return DB::transaction(function () use ($attempt, $exam, $responses) {
            $score = 0;

            foreach ($exam->examQuestions as $eq) {
                // Mark against the answer key of the EXACT version the student saw
                // (frozen at exam-build), not a later-corrected live answer.
                $correct  = $eq->questionVersion?->correct_answer ?? $eq->question?->correct_answer;
                $selected = $responses[$eq->question_id] ?? null;
                $selected = $selected ? strtoupper(substr($selected, 0, 1)) : null;
                $isCorrect = $selected !== null && $correct !== null && $selected === $correct;
                if ($isCorrect) {
                    $score++;
                }

                DB::table('v2_exam_answers')->updateOrInsert(
                    ['attempt_id' => $attempt->id, 'question_id' => $eq->question_id],
                    [
                        'selected_option' => $selected,
                        'correct_option'  => $correct,
                        'is_correct'      => $isCorrect,
                        'updated_at'      => now(),
                        'created_at'      => now(),
                    ]
                );
            }

            $attempt->update([
                'status'       => 'submitted',
                'score'        => $score,
                'submitted_at' => now(),
            ]);

            return $attempt->fresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Question voiding (marksheet engine)
    |--------------------------------------------------------------------------
    | The v2_exam_questions pivot is the single source of truth: is_voided removes
    | a question from every score, percentage and topic stat. Answers are never
    | touched. recomputeExamScores() re-derives each attempt's score/total from the
    | live (non-voided) questions, honouring the results-released line.
    */

    /**
     * Void one question on one exam (idempotent): flip the pivot flag, then
     * recompute the exam's marksheets. Student answers are untouched, and the
     * student/teacher QuestionFlag lifecycle is owned by the caller (the
     * "Send for Quality Review" action sets the reports to 'escalated').
     *
     * @return array<int,int> student ids whose VISIBLE score changed (post-release retro only)
     */
    public function voidExamQuestion(Exam $exam, int $questionId, int $teacherId, string $reason): array
    {
        $eq = ExamQuestion::where('exam_id', $exam->id)->where('question_id', $questionId)->first();
        if (! $eq || $eq->is_voided) {
            return [];
        }

        $eq->update([
            'is_voided'   => true,
            'void_reason' => $reason,
            'voided_by'   => $teacherId,
            'voided_at'   => now(),
        ]);

        $exam->loadMissing('school');

        return $this->recomputeExamScores($exam);
    }

    /**
     * Re-derive every submitted attempt's score/total for an exam from its live
     * (non-voided) questions, and ALWAYS write the visible marksheet. A void is a
     * deliberate, visible decision; the stored score/total feed every downstream
     * percentage, ranking, distribution and rollup, so they must reflect it. When
     * results were already released and a visible score moved, that student id is
     * returned so the caller can notify them (the change is never silent). Student
     * answers are never touched.
     *
     * @return array<int,int> student ids whose visible score changed on an already-released exam
     */
    public function recomputeExamScores(Exam $exam): array
    {
        $voidedIds = ExamQuestion::where('exam_id', $exam->id)->where('is_voided', true)->pluck('question_id')->all();
        $liveTotal = ExamQuestion::where('exam_id', $exam->id)->where('is_voided', false)->count();

        $released = $exam->resultsReleased();
        $changed  = [];

        $attempts = ExamAttempt::withoutGlobalScopes()
            ->where('exam_id', $exam->id)->where('status', 'submitted')->get();

        foreach ($attempts as $att) {
            $liveCorrect = (int) DB::table('v2_exam_answers')
                ->where('attempt_id', $att->id)->where('is_correct', 1)
                ->when($voidedIds, fn ($q) => $q->whereNotIn('question_id', $voidedIds))
                ->count();

            $scoreChanged = $att->score !== $liveCorrect || $att->total_questions !== $liveTotal;
            $att->score = $liveCorrect;
            $att->total_questions = $liveTotal;
            $att->save();

            if ($released && $scoreChanged) {
                $changed[] = (int) $att->student_id;
            }
        }

        return $changed;
    }

    /**
     * Read-only sibling of recomputeExamScores: given a set of question ids that
     * WOULD additionally be voided (on top of any already voided), return each
     * submitted attempt's before/after score & total without saving anything. Used
     * by the Phase 3 propagation preview and to capture audit before/after.
     *
     * @param  array<int>  $extraVoidedQuestionIds
     * @return array<int,array{attempt_id:int,student_id:int,score_before:int,total_before:int,score_after:int,total_after:int,released:bool,changed:bool}>
     */
    public function simulateRecompute(Exam $exam, array $extraVoidedQuestionIds): array
    {
        $voidedAfter = ExamQuestion::withoutGlobalScopes()
            ->where('exam_id', $exam->id)->where('is_voided', true)->pluck('question_id')
            ->merge($extraVoidedQuestionIds)->map(fn ($id) => (int) $id)->unique()->values()->all();

        $totalAfter = ExamQuestion::withoutGlobalScopes()
            ->where('exam_id', $exam->id)
            ->when($voidedAfter, fn ($q) => $q->whereNotIn('question_id', $voidedAfter))
            ->count();

        $released = $exam->resultsReleased();

        return ExamAttempt::withoutGlobalScopes()
            ->where('exam_id', $exam->id)->where('status', 'submitted')->get()
            ->map(function ($att) use ($voidedAfter, $totalAfter, $released) {
                $correctAfter = (int) DB::table('v2_exam_answers')
                    ->where('attempt_id', $att->id)->where('is_correct', 1)
                    ->when($voidedAfter, fn ($q) => $q->whereNotIn('question_id', $voidedAfter))
                    ->count();

                return [
                    'attempt_id'   => (int) $att->id,
                    'student_id'   => (int) $att->student_id,
                    'score_before' => (int) $att->score,
                    'total_before' => (int) $att->total_questions,
                    'score_after'  => $correctAfter,
                    'total_after'  => $totalAfter,
                    'released'     => $released,
                    'changed'      => ($att->score !== $correctAfter || $att->total_questions !== $totalAfter),
                ];
            })->all();
    }

    /** Per-topic breakdown for one attempt: [{topic, correct, total}]. */
    public function topicStatsForAttempt(ExamAttempt $attempt): array
    {
        return $this->topicStats(
            DB::table('v2_exam_answers as a')->where('a.attempt_id', $attempt->id)
        );
    }

    /** Per-topic breakdown across all submitted attempts of an exam (class-wide). */
    public function topicStatsForExam(Exam $exam): array
    {
        return $this->topicStats(
            DB::table('v2_exam_answers as a')
                ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
                ->where('at.exam_id', $exam->id)
                ->where('at.status', 'submitted')
        );
    }

    /**
     * Aggregate a student's performance: overall, then per subject, and within
     * each subject a per-topic breakdown and the list of tests taken.
     */
    public function studentStats(Student $student, ?\Illuminate\Support\Carbon $since = null, ?\Illuminate\Support\Carbon $until = null, ?array $subjectIds = null): array
    {
        // When $subjectIds is given (e.g. a teacher who only teaches certain
        // subjects), every aggregation below is scoped to those subjects so the
        // overall figures match the subject cards shown.
        $subjectScope = $subjectIds !== null ? array_values(array_unique($subjectIds)) : null;

        $attempts = ExamAttempt::where('student_id', $student->id)
            ->where('status', 'submitted')
            ->when($since, fn ($q) => $q->where('submitted_at', '>=', $since))
            ->when($until, fn ($q) => $q->where('submitted_at', '<=', $until))
            ->when($subjectScope !== null, fn ($q) => $q->whereHas('exam', fn ($e) => $e->whereIn('subject_id', $subjectScope)))
            ->with(['exam.subject', 'exam.topic'])
            ->orderByDesc('submitted_at')
            ->get();

        $topicRows = DB::table('v2_exam_answers as a')
            ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
            ->join('v2_questions as q', 'q.id', '=', 'a.question_id')
            ->leftJoin('v2_topics as t', 't.id', '=', 'q.topic_id')
            ->join('v2_exam_questions as veq', fn ($j) => $j->on('veq.exam_id', '=', 'at.exam_id')->on('veq.question_id', '=', 'a.question_id'))
            ->where('veq.is_voided', false)
            ->where('at.student_id', $student->id)
            ->where('at.status', 'submitted')
            ->when($since, fn ($q) => $q->where('at.submitted_at', '>=', $since))
            ->when($until, fn ($q) => $q->where('at.submitted_at', '<=', $until))
            ->when($subjectScope !== null, fn ($q) => $q->whereIn('q.subject_id', $subjectScope))
            ->selectRaw('q.subject_id, q.topic_id, t.external_id, t.title as topic, count(*) as total, sum(a.is_correct) as correct')
            ->groupBy('q.subject_id', 'q.topic_id', 't.external_id', 't.title')
            ->orderByRaw('CAST(t.external_id AS UNSIGNED)')
            ->get()
            ->groupBy('subject_id');

        // Per-subtopic accuracy, keyed by topic_id so it nests under each topic.
        $subtopicRows = DB::table('v2_exam_answers as a')
            ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
            ->join('v2_questions as q', 'q.id', '=', 'a.question_id')
            ->leftJoin('v2_subtopics as st', 'st.id', '=', 'q.subtopic_id')
            ->join('v2_exam_questions as veq', fn ($j) => $j->on('veq.exam_id', '=', 'at.exam_id')->on('veq.question_id', '=', 'a.question_id'))
            ->where('veq.is_voided', false)
            ->where('at.student_id', $student->id)
            ->where('at.status', 'submitted')
            ->when($since, fn ($q) => $q->where('at.submitted_at', '>=', $since))
            ->when($until, fn ($q) => $q->where('at.submitted_at', '<=', $until))
            ->when($subjectScope !== null, fn ($q) => $q->whereIn('q.subject_id', $subjectScope))
            ->selectRaw('q.topic_id, st.external_id, st.title as subtopic, count(*) as total, sum(a.is_correct) as correct')
            ->groupBy('q.topic_id', 'st.external_id', 'st.title')
            ->orderByRaw('CAST(st.external_id AS UNSIGNED)')
            ->get()
            ->groupBy('topic_id');

        // Full syllabus (every topic title) per subject, in syllabus order - lets the
        // narrative recommend prerequisite topics chosen only from real, existing topics.
        $subjectIds = $attempts->pluck('exam.subject_id')->filter()->unique()->all();
        $syllabus = DB::table('v2_topics')
            ->whereIn('subject_id', $subjectIds)
            ->orderByRaw('CAST(external_id AS UNSIGNED)')
            ->get(['subject_id', 'title'])
            ->groupBy('subject_id');

        $subjects = [];
        foreach ($attempts->groupBy(fn ($a) => $a->exam->subject_id) as $subjectId => $group) {
            $subjects[] = [
                'subject_id'  => $subjectId,
                'subject'     => $group->first()->exam->subject?->name ?? 'Subject',
                'tests_count' => $group->count(),
                'avg'         => (int) round($group->avg(fn ($a) => $a->percentage)),
                // Only topics that actually appeared in a test this period (total > 0) -
                // a topic never asked is omitted, not shown as 0%.
                'topics'      => collect($topicRows[$subjectId] ?? [])
                    ->filter(fn ($r) => (int) $r->total > 0)
                    ->map(fn ($r) => [
                        'topic'     => $r->topic ?? 'Untagged',
                        'correct'   => (int) $r->correct,
                        'total'     => (int) $r->total,
                        'percent'   => $r->total ? (int) round($r->correct / $r->total * 100) : 0,
                        // Subtopics that appeared under this topic this period (total > 0).
                        'subtopics' => collect($subtopicRows[$r->topic_id] ?? [])
                            ->filter(fn ($s) => (int) $s->total > 0)
                            ->map(fn ($s) => [
                                'subtopic' => $s->subtopic ?? 'Untagged',
                                'correct'  => (int) $s->correct,
                                'total'    => (int) $s->total,
                                'percent'  => $s->total ? (int) round($s->correct / $s->total * 100) : 0,
                            ])->values()->all(),
                    ])->values()->all(),
                // Every topic in this subject's syllabus (not just tested ones) - the pool
                // the narrative may draw prerequisite recommendations from.
                'all_topics'  => collect($syllabus[$subjectId] ?? [])->pluck('title')->all(),
                'tests'       => $group->map(fn ($a) => [
                    'exam_id' => $a->exam_id,
                    'title'   => $a->exam->title,
                    'topic'   => $a->exam->topic?->title ?? 'Mixed',
                    'score'   => $a->score,
                    'total'   => $a->total_questions,
                    'percent' => $a->percentage,
                    'date'    => $a->submitted_at,
                ])->all(),
            ];
        }

        return [
            'overall'  => [
                'tests' => $attempts->count(),
                'avg'   => $attempts->count() ? (int) round($attempts->avg(fn ($a) => $a->percentage)) : 0,
            ],
            'subjects' => $subjects,
        ];
    }

    /**
     * Everything the student dashboard needs in one shot:
     *   - overall: completed + missed tests, overall average, subject count
     *   - per subject: teacher, average, attempted/missed test counts, full topic
     *     coverage (every syllabus topic - examined / attempted / untouched), and
     *     the list of tests taken
     *   - upcoming: live + scheduled exams the student hasn't taken yet
     *
     * "Missed" = a released exam in the student's class whose window has closed
     * (available_until in the past) that the student never submitted. Kept separate
     * from studentStats() so the teacher attention generator is unaffected.
     */
    public function studentDashboard(Student $student): array
    {
        $empty = ['overall' => ['completed' => 0, 'missed' => 0, 'total' => 0, 'avg' => 0, 'subjects' => 0], 'subjects' => [], 'upcoming' => []];

        $classes = $student->classes()
            ->wherePivot('status', 'active')
            ->with(['subject', 'grade'])
            ->get();

        $classIds   = $classes->pluck('id')->all();
        $subjectIds = $classes->pluck('subject_id')->filter()->unique()->values()->all();

        if (empty($classIds)) {
            return $empty;
        }

        // Teacher name(s) per subject, primary first (via the student's class for that subject).
        $teacherBySubject = DB::table('v2_class_teachers as ct')
            ->join('v2_classes as c', 'c.id', '=', 'ct.class_id')
            ->join('v2_teachers as t', 't.id', '=', 'ct.teacher_id')
            ->whereIn('ct.class_id', $classIds)
            ->orderByDesc('ct.is_primary')
            ->select('c.subject_id', 't.name')
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($rows) => $rows->pluck('name')->unique()->implode(', '));

        // Full syllabus topics per subject, in syllabus order - the coverage reference length.
        $syllabus = Topic::whereIn('subject_id', $subjectIds)
            ->orderBy('sort_order')->orderByRaw('CAST(external_id AS UNSIGNED)')
            ->get(['id', 'title', 'subject_id'])
            ->groupBy('subject_id');

        // Released exams in the student's classes (the universe of what's been set).
        $exams = Exam::published()
            ->whereIn('class_id', $classIds)
            ->with(['subject', 'creator'])
            ->get();

        // The student's submitted attempts for those exams, keyed by exam_id.
        $attempts = ExamAttempt::where('student_id', $student->id)
            ->where('status', 'submitted')
            ->whereIn('exam_id', $exams->pluck('id'))
            ->get()
            ->keyBy('exam_id');

        // Per (subject, topic) accuracy from the student's answers.
        $topicRows = DB::table('v2_exam_answers as a')
            ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
            ->join('v2_questions as q', 'q.id', '=', 'a.question_id')
            ->join('v2_exam_questions as veq', fn ($j) => $j->on('veq.exam_id', '=', 'at.exam_id')->on('veq.question_id', '=', 'a.question_id'))
            ->where('veq.is_voided', false)
            ->where('at.student_id', $student->id)
            ->where('at.status', 'submitted')
            ->whereIn('q.subject_id', $subjectIds)
            ->selectRaw('q.subject_id, q.topic_id, count(*) total, sum(a.is_correct) correct')
            ->groupBy('q.subject_id', 'q.topic_id')
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($rows) => $rows->keyBy('topic_id'));

        // Topics that have appeared in any released exam (examined), per subject.
        $examinedBySubject = DB::table('v2_exam_questions as eq')
            ->join('v2_exams as e', 'e.id', '=', 'eq.exam_id')
            ->join('v2_questions as q', 'q.id', '=', 'eq.question_id')
            ->whereIn('e.class_id', $classIds)
            ->where('e.status', 'released')
            ->select('e.subject_id', 'q.topic_id')
            ->distinct()
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($rows) => $rows->pluck('topic_id')->filter()->map(fn ($id) => (int) $id)->all());

        $completed = $missedTotal = 0;
        $subjects = $upcoming = [];

        foreach ($classes->groupBy('subject_id') as $subjectId => $subjectClasses) {
            $subjectId   = (int) $subjectId;
            $subjectName = $subjectClasses->first()->subject?->name ?? 'Subject';
            $subjectExams = $exams->where('subject_id', $subjectId);

            $attemptedTests = [];
            $missedCount = 0;

            foreach ($subjectExams as $exam) {
                $attempt = $attempts->get($exam->id);
                if ($attempt) {
                    $attemptedTests[] = [
                        'exam_id' => $exam->id,
                        'title'   => $exam->title,
                        'score'   => $attempt->score,
                        'total'   => $attempt->total_questions,
                        'percent' => $attempt->percentage,
                        'date'    => $attempt->submitted_at,
                        'results' => $exam->resultsReleased(),
                    ];
                } elseif ($exam->isExpired()) {
                    $missedCount++;
                } elseif ($exam->isLive() || $exam->isScheduled()) {
                    $upcoming[] = [
                        'exam_id'        => $exam->id,
                        'title'          => $exam->title,
                        'subject'        => $subjectName,
                        'teacher'        => $exam->creator?->name,
                        'status'         => $exam->isLive() ? 'live' : 'scheduled',
                        'available_from' => $exam->available_from,
                        'due'            => $exam->available_until,
                    ];
                }
            }

            usort($attemptedTests, fn ($a, $b) => $b['date'] <=> $a['date']);

            $completed   += count($attemptedTests);
            $missedTotal += $missedCount;

            $examinedSet = $examinedBySubject[$subjectId] ?? [];
            $topicStat   = $topicRows[$subjectId] ?? collect();
            $syl         = $syllabus[$subjectId] ?? collect();

            $topics = [];
            $attemptedTopics = 0;
            foreach ($syl as $t) {
                $row       = $topicStat->get($t->id);
                $attempted = $row && (int) $row->total > 0;
                if ($attempted) {
                    $attemptedTopics++;
                }
                $topics[] = [
                    'topic'     => $t->title,
                    'examined'  => in_array((int) $t->id, $examinedSet, true),
                    'attempted' => (bool) $attempted,
                    'correct'   => $attempted ? (int) $row->correct : 0,
                    'total'     => $attempted ? (int) $row->total : 0,
                    'percent'   => $attempted && $row->total ? (int) round($row->correct / $row->total * 100) : 0,
                ];
            }

            $subjects[] = [
                'subject_id'       => $subjectId,
                'subject'          => $subjectName,
                'teacher'          => $teacherBySubject[$subjectId] ?? null,
                'avg'              => count($attemptedTests) ? (int) round(collect($attemptedTests)->avg('percent')) : 0,
                'tests_count'      => count($attemptedTests),
                'missed_count'     => $missedCount,
                'total_topics'     => $syl->count(),
                'examined_topics'  => count($examinedSet),
                'attempted_topics' => $attemptedTopics,
                'topics'           => $topics,
                'tests'            => $attemptedTests,
            ];
        }

        usort($subjects, fn ($a, $b) => strcmp($a['subject'], $b['subject']));

        // Soonest first: live before scheduled, then by the next relevant date.
        $far = now()->addCentury();
        usort($upcoming, function ($a, $b) use ($far) {
            $rank = fn ($x) => $x['status'] === 'live' ? 0 : 1;
            if ($rank($a) !== $rank($b)) {
                return $rank($a) <=> $rank($b);
            }
            $key = fn ($x) => $x['status'] === 'live' ? ($x['due'] ?? $far) : ($x['available_from'] ?? $far);

            return $key($a) <=> $key($b);
        });

        return [
            'overall' => [
                'completed' => $completed,
                'missed'    => $missedTotal,
                'total'     => $completed + $missedTotal,
                'avg'       => $attempts->count() ? (int) round($attempts->avg(fn ($a) => $a->percentage)) : 0,
                'subjects'  => count($subjects),
            ],
            'subjects' => $subjects,
            'upcoming' => $upcoming,
        ];
    }

    /** Last $limit submitted attempts in chronological order - for a trend line. */
    public function studentScoreTrend(int $studentId, int $limit = 10): array
    {
        return ExamAttempt::where('student_id', $studentId)
            ->where('status', 'submitted')
            ->with(['exam:id,title,subject_id', 'exam.subject:id,name'])
            ->orderByDesc('submitted_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($a) => [
                'label'   => $a->exam?->title ?? 'Exam',
                'score'   => $a->percentage,
                'date'    => $a->submitted_at?->format('d M'),
                'subject' => $a->exam?->subject?->name ?? '',
            ])->all();
    }

    /** Flat per-topic stats for one student across all submitted attempts. */
    public function studentTopicStats(int $studentId): array
    {
        return $this->topicStats(
            DB::table('v2_exam_answers as a')
                ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
                ->where('at.student_id', $studentId)
                ->where('at.status', 'submitted')
        );
    }

    /** Exams submitted per month for one calendar year: 12 ints (Jan..Dec). */
    public function studentMonthlyActivity(int $studentId, ?int $year = null): array
    {
        $year ??= (int) now()->year;

        $counts = ExamAttempt::where('student_id', $studentId)
            ->where('status', 'submitted')
            ->whereYear('submitted_at', $year)
            ->selectRaw('MONTH(submitted_at) as m, count(*) as c')
            ->groupBy('m')->pluck('c', 'm');

        return array_map(fn ($m) => (int) ($counts[$m] ?? 0), range(1, 12));
    }

    /** Avg% of the last 5 submitted attempts minus the previous 5. Null if too little history. */
    public function studentImprovement(int $studentId): ?float
    {
        $base = fn () => ExamAttempt::where('student_id', $studentId)
            ->where('status', 'submitted')->orderByDesc('submitted_at');

        $recent   = $base()->limit(5)->get();
        $previous = $base()->offset(5)->limit(5)->get();

        if ($recent->isEmpty() || $previous->isEmpty()) {
            return null;
        }

        return round($recent->avg(fn ($a) => $a->percentage) - $previous->avg(fn ($a) => $a->percentage), 1);
    }

    /** Per-topic stats aggregated across every submitted attempt in a whole school. */
    public function schoolTopicStats(int $schoolId, ?int $branchId = null): array
    {
        return $this->topicStats($this->schoolAnswersBase($schoolId, $branchId));
    }

    /** Per-topic stats across one teacher's exams (their students' results by topic). */
    public function teacherTopicStats(int $teacherId): array
    {
        return $this->topicStats(
            DB::table('v2_exam_answers as a')
                ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
                ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
                ->where('e.created_by', $teacherId)
                ->where('at.status', 'submitted')
        );
    }

    /** Completion rate (submitted / started) + exams-this-month for one teacher. */
    public function teacherEngagement(int $teacherId): array
    {
        $attempts = DB::table('v2_exam_attempts as at')->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('e.created_by', $teacherId);

        $total     = (clone $attempts)->count();
        $submitted = (clone $attempts)->where('at.status', 'submitted')->count();

        return [
            'completion'       => $total ? (int) round($submitted / $total * 100) : 0,
            'exams_this_month' => DB::table('v2_exams')->where('created_by', $teacherId)
                ->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count(),
        ];
    }

    /** Score distribution (5 bins: 0-20,21-40,41-60,61-80,81-100) over a teacher's submitted attempts. */
    public function teacherScoreDistribution(int $teacherId): array
    {
        $pcts = DB::table('v2_exam_attempts as at')->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('e.created_by', $teacherId)->where('at.status', 'submitted')
            ->where('at.total_questions', '>', 0)
            ->selectRaw('at.score / at.total_questions * 100 as pct')->pluck('pct');

        $bins = [0, 0, 0, 0, 0];
        foreach ($pcts as $p) {
            $bins[$p <= 20 ? 0 : ($p <= 40 ? 1 : ($p <= 60 ? 2 : ($p <= 80 ? 3 : 4)))]++;
        }

        return $bins;
    }

    /** Per-exam avg% + submission count over time (oldest first), for a combo chart. */
    public function teacherExamTrend(int $teacherId, int $limit = 15): array
    {
        return DB::table('v2_exams as e')
            ->leftJoin('v2_exam_attempts as at', fn ($j) => $j->on('at.exam_id', '=', 'e.id')->where('at.status', 'submitted'))
            ->where('e.created_by', $teacherId)
            ->groupBy('e.id', 'e.title', 'e.created_at')
            ->orderBy('e.created_at')
            ->limit($limit)
            ->selectRaw('e.title, e.created_at, count(at.id) as cnt, avg(at.score / nullif(at.total_questions,0)) * 100 as avg_pct')
            ->get()
            ->map(fn ($r) => [
                'title' => $r->title,
                'date'  => \Illuminate\Support\Carbon::parse($r->created_at)->format('d M'),
                'count' => (int) $r->cnt,
                'avg'   => $r->avg_pct !== null ? (int) round($r->avg_pct) : null,
            ])->all();
    }

    /** Per-student avg across a teacher's exams (their enrolled students), worst-first. */
    public function teacherStudentPerformance(int $teacherId): array
    {
        $classIds = DB::table('v2_class_teachers')->where('teacher_id', $teacherId)->pluck('class_id');
        if ($classIds->isEmpty()) {
            return [];
        }

        $studentIds = DB::table('v2_student_enrollments')->whereIn('class_id', $classIds)
            ->where('status', 'active')->distinct()->pluck('student_id');

        $perf = DB::table('v2_exam_attempts as at')->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('e.created_by', $teacherId)->where('at.status', 'submitted')
            ->selectRaw('at.student_id, count(*) attempts, avg(at.score / nullif(at.total_questions,0)) * 100 avg_pct')
            ->groupBy('at.student_id')->get()->keyBy('student_id');

        return Student::whereIn('id', $studentIds)->orderBy('name')->get(['id', 'name', 'roll_number'])
            ->map(fn ($s) => [
                'id'       => $s->id,
                'name'     => $s->name,
                'roll'     => $s->roll_number,
                'attempts' => (int) ($perf[$s->id]->attempts ?? 0),
                'avg'      => isset($perf[$s->id]) && $perf[$s->id]->avg_pct !== null ? (int) round($perf[$s->id]->avg_pct) : null,
            ])
            ->sortBy(fn ($r) => $r['avg'] ?? 999)->values()->all();
    }

    /** Overview totals for one teacher (their classes/students/exams + avg). */
    public function teacherOverview(int $teacherId): array
    {
        $classIds = DB::table('v2_class_teachers')->where('teacher_id', $teacherId)->pluck('class_id');

        $students = $classIds->isEmpty() ? 0 : (int) DB::table('v2_student_enrollments')
            ->whereIn('class_id', $classIds)->where('status', 'active')->distinct()->count('student_id');

        $perf = DB::table('v2_exam_attempts as at')->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('e.created_by', $teacherId)->where('at.status', 'submitted')
            ->selectRaw('count(*) submissions, avg(at.score / nullif(at.total_questions,0)) * 100 avg_pct')->first();

        return [
            'classes'     => $classIds->count(),
            'students'    => $students,
            'exams'       => DB::table('v2_exams')->where('created_by', $teacherId)->count(),
            'submissions' => (int) ($perf->submissions ?? 0),
            'avg'         => ($perf && $perf->avg_pct !== null) ? (int) round($perf->avg_pct) : null,
        ];
    }

    /** School-wide totals (optionally narrowed to one branch). */
    public function schoolOverview(int $schoolId, ?int $branchId = null): array
    {
        $ids = $branchId ? $this->branchClassIds($branchId) : null;

        $avg = DB::table('v2_exam_attempts as at')
            ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('e.school_id', $schoolId)->where('at.status', 'submitted')
            ->when($ids !== null, fn ($q) => $q->whereIn('e.class_id', $ids))
            ->selectRaw('count(*) submissions, avg(at.score / nullif(at.total_questions,0)) * 100 as avg_pct')->first();

        return [
            'students'    => DB::table('v2_students')->where('school_id', $schoolId)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count(),
            'teachers'    => DB::table('v2_teachers')->where('school_id', $schoolId)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count(),
            'classes'     => DB::table('v2_classes')->where('school_id', $schoolId)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->count(),
            'exams'       => DB::table('v2_exams')->where('school_id', $schoolId)->when($ids !== null, fn ($q) => $q->whereIn('class_id', $ids))->count(),
            'submissions' => (int) ($avg->submissions ?? 0),
            'avg'         => ($avg && $avg->avg_pct !== null) ? (int) round($avg->avg_pct) : null,
        ];
    }

    /** Per-teacher summary rows for the school (optionally narrowed to a branch). */
    public function schoolTeacherRows(int $schoolId, ?int $branchId = null): array
    {
        $exams = DB::table('v2_exams')->where('school_id', $schoolId)
            ->selectRaw('created_by, count(*) as exams, count(distinct class_id) as classes')
            ->groupBy('created_by')->get()->keyBy('created_by');

        $perf = DB::table('v2_exam_attempts as at')->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('e.school_id', $schoolId)->where('at.status', 'submitted')
            ->selectRaw('e.created_by, count(*) submissions, avg(at.score / at.total_questions) * 100 as avg_pct')
            ->groupBy('e.created_by')->get()->keyBy('created_by');

        return DB::table('v2_teachers')->where('school_id', $schoolId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($t) => [
                'id'          => $t->id,
                'name'        => $t->name,
                'classes'     => (int) ($exams[$t->id]->classes ?? 0),
                'exams'       => (int) ($exams[$t->id]->exams ?? 0),
                'submissions' => (int) ($perf[$t->id]->submissions ?? 0),
                'avg'         => isset($perf[$t->id]) && $perf[$t->id]->avg_pct !== null ? (int) round($perf[$t->id]->avg_pct) : null,
            ])->all();
    }

    /** Per-branch summary rows for one school (students/teachers/classes/exams + avg). */
    public function schoolBranchRows(int $schoolId): array
    {
        return DB::table('v2_branches')->where('school_id', $schoolId)->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name] + $this->schoolOverview($schoolId, $b->id))
            ->all();
    }

    /** Per-class summary rows for the school (optionally narrowed to a branch). */
    public function schoolClassRows(int $schoolId, ?int $branchId = null): array
    {
        $perf = DB::table('v2_exam_attempts as at')->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('e.school_id', $schoolId)->where('at.status', 'submitted')
            ->selectRaw('e.class_id, avg(at.score / at.total_questions) * 100 as avg_pct')
            ->groupBy('e.class_id')->get()->keyBy('class_id');

        $examCounts = DB::table('v2_exams')->where('school_id', $schoolId)
            ->selectRaw('class_id, count(*) c')->groupBy('class_id')->pluck('c', 'class_id');

        // class -> teacher names via raw join (avoids the BelongsToMany school_id ambiguity)
        $classTeachers = DB::table('v2_class_teachers as ct')->join('v2_teachers as tt', 'tt.id', '=', 'ct.teacher_id')
            ->where('ct.school_id', $schoolId)->select('ct.class_id', 'tt.name')->get()->groupBy('class_id');

        return SchoolClass::where('school_id', $schoolId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['grade', 'subject'])
            ->withCount(['enrollments as student_count' => fn ($e) => $e->where('status', 'active')])
            ->orderBy('name')->get()
            ->map(fn ($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'grade'    => $c->grade?->name,
                'subject'  => $c->subject?->name,
                'teacher'  => ($classTeachers[$c->id] ?? collect())->pluck('name')->implode(', ') ?: '-',
                'students' => $c->student_count,
                'exams'    => (int) ($examCounts[$c->id] ?? 0),
                'avg'      => isset($perf[$c->id]) && $perf[$c->id]->avg_pct !== null ? (int) round($perf[$c->id]->avg_pct) : null,
            ])->all();
    }

    /** Class ids belonging to one branch (for branch-scoped roll-ups). */
    private function branchClassIds(int $branchId): array
    {
        return DB::table('v2_classes')->where('branch_id', $branchId)->pluck('id')->all();
    }

    private function schoolAnswersBase(int $schoolId, ?int $branchId = null)
    {
        return DB::table('v2_exam_answers as a')
            ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
            ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('e.school_id', $schoolId)
            ->where('at.status', 'submitted')
            ->when($branchId, fn ($q) => $q->whereIn('e.class_id', $this->branchClassIds($branchId)));
    }

    /** Per-topic stats aggregated across every submitted attempt in a class. */
    public function classTopicStats(SchoolClass $class): array
    {
        return $this->topicStats(
            DB::table('v2_exam_answers as a')
                ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
                ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
                ->where('e.class_id', $class->id)
                ->where('at.status', 'submitted')
        );
    }

    /**
     * Per-student × per-topic matrix for a class (across all its exams):
     * ['topics' => [titles...], 'rows' => [['student','roll','overall','attempted','cells'=>[topic=>{...}|null]]]].
     */
    public function classStudentMatrix(SchoolClass $class): array
    {
        $rows = DB::table('v2_exam_answers as a')
            ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
            ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->join('v2_questions as q', 'q.id', '=', 'a.question_id')
            ->leftJoin('v2_topics as t', 't.id', '=', 'q.topic_id')
            ->join('v2_exam_questions as veq', fn ($j) => $j->on('veq.exam_id', '=', 'at.exam_id')->on('veq.question_id', '=', 'a.question_id'))
            ->where('veq.is_voided', false)
            ->where('e.class_id', $class->id)
            ->where('at.status', 'submitted')
            ->selectRaw('at.student_id, t.external_id, COALESCE(t.title, "Untagged") as topic, count(*) total, sum(a.is_correct) correct')
            ->groupBy('at.student_id', 't.external_id', 't.title')
            ->orderByRaw('CAST(t.external_id AS UNSIGNED)')
            ->get();

        $topicCols = [];
        foreach ($rows as $r) {
            if (! in_array($r->topic, $topicCols, true)) {
                $topicCols[] = $r->topic;
            }
        }

        $byStudent = $rows->groupBy('student_id');
        $studentIds = StudentEnrollment::where('class_id', $class->id)->pluck('student_id');
        $students = Student::whereIn('id', $studentIds)->orderBy('name')->get(['id', 'name', 'roll_number']);

        $matrix = [];
        foreach ($students as $st) {
            $sr = $byStudent[$st->id] ?? collect();
            $cells = [];
            $tc = $cc = 0;
            foreach ($topicCols as $topic) {
                $row = $sr->firstWhere('topic', $topic);
                if ($row) {
                    $cells[$topic] = [
                        'correct' => (int) $row->correct,
                        'total'   => (int) $row->total,
                        'percent' => $row->total ? (int) round($row->correct / $row->total * 100) : 0,
                    ];
                    $tc += $row->total;
                    $cc += $row->correct;
                } else {
                    $cells[$topic] = null;
                }
            }
            $matrix[] = [
                'id'        => $st->id,
                'student'   => $st->name,
                'roll'      => $st->roll_number,
                'overall'   => $tc ? (int) round($cc / $tc * 100) : null,
                'attempted' => $tc > 0,
                'cells'     => $cells,
            ];
        }

        return ['topics' => $topicCols, 'rows' => $matrix];
    }

    /*
    |--------------------------------------------------------------------------
    | Super Admin drill-down aggregates (grade / subject / topic / single-mixed)
    |--------------------------------------------------------------------------
    | All raw DB::table (so no global scope fires) and keyed on explicit ids, so
    | the unscoped Super Admin can call them for any school. New helpers use
    | nullif(total_questions,0) to avoid the divide-by-zero the older school*
    | rollups can hit on a 0-question exam.
    */

    /** Per-grade summary rows for one school (optionally narrowed to a branch). */
    public function gradeWideRows(int $schoolId, ?int $branchId = null): array
    {
        $classCounts = DB::table('v2_classes')->where('school_id', $schoolId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('grade_id, count(*) c')->groupBy('grade_id')->pluck('c', 'grade_id');

        $studentCounts = DB::table('v2_student_enrollments as se')
            ->join('v2_classes as c', 'c.id', '=', 'se.class_id')
            ->where('c.school_id', $schoolId)->where('se.status', 'active')
            ->when($branchId, fn ($q) => $q->where('c.branch_id', $branchId))
            ->selectRaw('c.grade_id, count(distinct se.student_id) c')
            ->groupBy('c.grade_id')->pluck('c', 'grade_id');

        $examCounts = DB::table('v2_exams as e')
            ->join('v2_classes as c', 'c.id', '=', 'e.class_id')
            ->where('e.school_id', $schoolId)
            ->when($branchId, fn ($q) => $q->where('c.branch_id', $branchId))
            ->selectRaw('c.grade_id, count(*) c')->groupBy('c.grade_id')->pluck('c', 'grade_id');

        $perf = DB::table('v2_exam_attempts as at')
            ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->join('v2_classes as c', 'c.id', '=', 'e.class_id')
            ->where('e.school_id', $schoolId)->where('at.status', 'submitted')
            ->when($branchId, fn ($q) => $q->where('c.branch_id', $branchId))
            ->selectRaw('c.grade_id, count(*) submissions, avg(at.score / nullif(at.total_questions,0)) * 100 avg_pct')
            ->groupBy('c.grade_id')->get()->keyBy('grade_id');

        return DB::table('v2_grades')->where('school_id', $schoolId)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($g) => [
                'id'          => $g->id,
                'grade'       => $g->name,
                'classes'     => (int) ($classCounts[$g->id] ?? 0),
                'students'    => (int) ($studentCounts[$g->id] ?? 0),
                'exams'       => (int) ($examCounts[$g->id] ?? 0),
                'submissions' => (int) ($perf[$g->id]->submissions ?? 0),
                'avg'         => isset($perf[$g->id]) && $perf[$g->id]->avg_pct !== null ? (int) round($perf[$g->id]->avg_pct) : null,
            ])->all();
    }

    /** Per-topic stats restricted to one grade's classes within a school. */
    public function gradeTopicStats(int $schoolId, int $gradeId, ?int $branchId = null): array
    {
        return $this->topicStats(
            DB::table('v2_exam_answers as a')
                ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
                ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
                ->join('v2_classes as c', 'c.id', '=', 'e.class_id')
                ->where('e.school_id', $schoolId)
                ->where('c.grade_id', $gradeId)
                ->where('at.status', 'submitted')
                ->when($branchId, fn ($q) => $q->where('c.branch_id', $branchId))
        );
    }

    /** Per-subject summary rows for one school (optionally narrowed to a branch). */
    public function subjectWideRows(int $schoolId, ?int $branchId = null): array
    {
        $ids = $branchId ? $this->branchClassIds($branchId) : null;

        $classCounts = DB::table('v2_classes')->where('school_id', $schoolId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('subject_id, count(*) c')->groupBy('subject_id')->pluck('c', 'subject_id');

        $studentCounts = DB::table('v2_student_enrollments as se')
            ->join('v2_classes as c', 'c.id', '=', 'se.class_id')
            ->where('c.school_id', $schoolId)->where('se.status', 'active')
            ->when($branchId, fn ($q) => $q->where('c.branch_id', $branchId))
            ->selectRaw('c.subject_id, count(distinct se.student_id) c')
            ->groupBy('c.subject_id')->pluck('c', 'subject_id');

        $examCounts = DB::table('v2_exams')->where('school_id', $schoolId)
            ->when($ids !== null, fn ($q) => $q->whereIn('class_id', $ids))
            ->selectRaw('subject_id, count(*) c')->groupBy('subject_id')->pluck('c', 'subject_id');

        $perf = DB::table('v2_exam_attempts as at')->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('e.school_id', $schoolId)->where('at.status', 'submitted')
            ->when($ids !== null, fn ($q) => $q->whereIn('e.class_id', $ids))
            ->selectRaw('e.subject_id, count(*) submissions, avg(at.score / nullif(at.total_questions,0)) * 100 avg_pct')
            ->groupBy('e.subject_id')->get()->keyBy('subject_id');

        $subjectIds = $classCounts->keys()->merge($examCounts->keys())->unique()->filter()->values();
        $names = DB::table('v2_subjects')->whereIn('id', $subjectIds)->pluck('name', 'id');

        return $subjectIds
            ->map(fn ($sid) => [
                'id'          => (int) $sid,
                'subject'     => $names[$sid] ?? 'Subject',
                'classes'     => (int) ($classCounts[$sid] ?? 0),
                'students'    => (int) ($studentCounts[$sid] ?? 0),
                'exams'       => (int) ($examCounts[$sid] ?? 0),
                'submissions' => (int) ($perf[$sid]->submissions ?? 0),
                'avg'         => isset($perf[$sid]) && $perf[$sid]->avg_pct !== null ? (int) round($perf[$sid]->avg_pct) : null,
            ])
            ->sortBy('subject')->values()->all();
    }

    /**
     * Per-topic stats within one subject. $schoolId null => platform-wide
     * (across all schools); an int restricts to that school.
     */
    public function subjectTopicStats(?int $schoolId, int $subjectId, ?int $branchId = null): array
    {
        return $this->topicStats(
            DB::table('v2_exam_answers as a')
                ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
                ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
                ->where('e.subject_id', $subjectId)
                ->where('at.status', 'submitted')
                ->when($schoolId, fn ($q) => $q->where('e.school_id', $schoolId))
                ->when($branchId, fn ($q) => $q->whereIn('e.class_id', $this->branchClassIds($branchId)))
        );
    }

    /**
     * Answer-weighted per-topic rollup across all subjects. $schoolId null =>
     * platform-wide; an int restricts to that school.
     */
    public function topicWideStats(?int $schoolId = null): array
    {
        $base = DB::table('v2_exam_answers as a')
            ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
            ->where('at.status', 'submitted');

        if ($schoolId !== null) {
            $base->join('v2_exams as e', 'e.id', '=', 'at.exam_id')->where('e.school_id', $schoolId);
        }

        return $this->topicStats($base);
    }

    /**
     * Same answer-weighted topic accuracy, but GROUPED BY SUBJECT - so the
     * platform-wide rollup (which spans Physics, Chemistry, Biology, …) reads as
     * one block per subject instead of a single mixed list. Each group carries the
     * subject name/code/level, an overall accuracy, and its per-topic rows
     * (untagged questions roll up as "Untagged" within their own subject). Groups
     * are ordered by volume (most-answered subject first).
     *
     * @return array<int,array{subject:string,code:?string,level:?string,correct:int,total:int,percent:int,topics:array<int,array{topic:string,correct:int,total:int,percent:int}>}>
     */
    public function topicWideStatsBySubject(?int $schoolId = null): array
    {
        $base = DB::table('v2_exam_answers as a')
            ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
            ->where('at.status', 'submitted');

        if ($schoolId !== null) {
            $base->join('v2_exams as e', 'e.id', '=', 'at.exam_id')->where('e.school_id', $schoolId);
        }

        return $this->topicStatsBySubject($base);
    }

    /** Subject-grouped topic accuracy for one school (optionally narrowed to a branch). */
    public function schoolTopicStatsBySubject(int $schoolId, ?int $branchId = null): array
    {
        return $this->topicStatsBySubject($this->schoolAnswersBase($schoolId, $branchId));
    }

    /**
     * Shared engine for the subject-grouped topic rollups: takes an answers base
     * query (already scoped: platform / school / branch) and returns one block per
     * subject - subject name/code/level, overall accuracy, and its per-topic rows
     * (untagged questions roll up as "Untagged" within their own subject). Groups
     * are ordered by volume (most-answered subject first).
     *
     * @return array<int,array{subject:string,code:?string,level:?string,correct:int,total:int,percent:int,topics:array<int,array{topic:string,correct:int,total:int,percent:int}>}>
     */
    private function topicStatsBySubject($query): array
    {
        $rows = $query
            ->join('v2_questions as q', 'q.id', '=', 'a.question_id')
            ->join('v2_subjects as s', 's.id', '=', 'q.subject_id')
            ->leftJoin('v2_topics as t', 't.id', '=', 'q.topic_id')
            // Voided exam-questions are excluded from the subject-grouped topic rollups too.
            ->join('v2_exam_attempts as veat', 'veat.id', '=', 'a.attempt_id')
            ->join('v2_exam_questions as veq', fn ($j) => $j->on('veq.exam_id', '=', 'veat.exam_id')->on('veq.question_id', '=', 'a.question_id'))
            ->where('veq.is_voided', false)
            ->selectRaw('s.id as subject_id, s.name as subject, s.code, s.level, t.external_id, t.title, count(*) as total, sum(a.is_correct) as correct')
            ->groupBy('s.id', 's.name', 's.code', 's.level', 't.external_id', 't.title')
            ->orderByRaw('t.external_id IS NULL, CAST(t.external_id AS UNSIGNED)') // tagged by syllabus number, untagged last
            ->get();

        $groups = [];
        foreach ($rows as $r) {
            $sid = (int) $r->subject_id;
            $groups[$sid] ??= ['subject' => $r->subject, 'code' => $r->code, 'level' => $r->level, 'correct' => 0, 'total' => 0, 'topics' => []];
            $groups[$sid]['topics'][] = [
                'topic'   => $r->title ?? 'Untagged',
                'correct' => (int) $r->correct,
                'total'   => (int) $r->total,
                'percent' => $r->total ? (int) round($r->correct / $r->total * 100) : 0,
            ];
            $groups[$sid]['correct'] += (int) $r->correct;
            $groups[$sid]['total']   += (int) $r->total;
        }

        $out = array_map(function ($g) {
            $g['percent'] = $g['total'] ? (int) round($g['correct'] / $g['total'] * 100) : 0;

            return $g;
        }, array_values($groups));

        usort($out, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $out;
    }

    /**
     * Cross-school grade rollup. Grouped by grade NAME (grade_id is per-school
     * and not comparable across schools), so differently-named grades won't merge.
     */
    public function gradePlatformRows(): array
    {
        $classAgg = DB::table('v2_classes as c')
            ->join('v2_grades as g', 'g.id', '=', 'c.grade_id')
            ->selectRaw('g.name, count(distinct c.school_id) schools, count(*) classes')
            ->groupBy('g.name')->get()->keyBy('name');

        $studentAgg = DB::table('v2_student_enrollments as se')
            ->join('v2_classes as c', 'c.id', '=', 'se.class_id')
            ->join('v2_grades as g', 'g.id', '=', 'c.grade_id')
            ->where('se.status', 'active')
            ->selectRaw('g.name, count(distinct se.student_id) students')
            ->groupBy('g.name')->get()->keyBy('name');

        $examAgg = DB::table('v2_exams as e')
            ->join('v2_classes as c', 'c.id', '=', 'e.class_id')
            ->join('v2_grades as g', 'g.id', '=', 'c.grade_id')
            ->selectRaw('g.name, count(*) exams')
            ->groupBy('g.name')->get()->keyBy('name');

        $perf = DB::table('v2_exam_attempts as at')
            ->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->join('v2_classes as c', 'c.id', '=', 'e.class_id')
            ->join('v2_grades as g', 'g.id', '=', 'c.grade_id')
            ->where('at.status', 'submitted')
            ->selectRaw('g.name, count(*) submissions, avg(at.score / nullif(at.total_questions,0)) * 100 avg_pct')
            ->groupBy('g.name')->get()->keyBy('name');

        return $classAgg->keys()->map(fn ($name) => [
            'grade'       => $name,
            'schools'     => (int) ($classAgg[$name]->schools ?? 0),
            'classes'     => (int) ($classAgg[$name]->classes ?? 0),
            'students'    => (int) ($studentAgg[$name]->students ?? 0),
            'exams'       => (int) ($examAgg[$name]->exams ?? 0),
            'submissions' => (int) ($perf[$name]->submissions ?? 0),
            'avg'         => isset($perf[$name]) && $perf[$name]->avg_pct !== null ? (int) round($perf[$name]->avg_pct) : null,
        ])->sortByDesc('submissions')->values()->all();
    }

    /** Cross-school subject rollup, grouped by the global subject id. */
    public function subjectPlatformRows(): array
    {
        $examAgg = DB::table('v2_exams')
            ->selectRaw('subject_id, count(distinct school_id) schools, count(*) exams')
            ->groupBy('subject_id')->get()->keyBy('subject_id');

        $perf = DB::table('v2_exam_attempts as at')->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('at.status', 'submitted')
            ->selectRaw('e.subject_id, count(*) submissions, avg(at.score / nullif(at.total_questions,0)) * 100 avg_pct')
            ->groupBy('e.subject_id')->get()->keyBy('subject_id');

        $names = DB::table('v2_subjects')->pluck('name', 'id');

        return $examAgg->keys()->filter()->map(fn ($sid) => [
            'id'          => (int) $sid,
            'subject'     => $names[$sid] ?? 'Subject',
            'schools'     => (int) ($examAgg[$sid]->schools ?? 0),
            'exams'       => (int) ($examAgg[$sid]->exams ?? 0),
            'submissions' => (int) ($perf[$sid]->submissions ?? 0),
            'avg'         => isset($perf[$sid]) && $perf[$sid]->avg_pct !== null ? (int) round($perf[$sid]->avg_pct) : null,
        ])->sortByDesc('submissions')->values()->all();
    }

    /**
     * Single-topic vs mixed-topic exam split (mixed = topic_id IS NULL).
     * Optional $schoolId / $classId narrow the scope; null = platform-wide.
     */
    public function examKindSplit(?int $schoolId = null, ?int $classId = null, ?int $createdBy = null, ?int $branchId = null): array
    {
        $ids = $branchId ? $this->branchClassIds($branchId) : null;

        $examRows = DB::table('v2_exams')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->when($createdBy, fn ($q) => $q->where('created_by', $createdBy))
            ->when($ids !== null, fn ($q) => $q->whereIn('class_id', $ids))
            ->selectRaw('(topic_id IS NULL) as is_mixed, count(*) exams')
            ->groupBy('is_mixed')->get()->keyBy('is_mixed');

        $perfRows = DB::table('v2_exam_attempts as at')->join('v2_exams as e', 'e.id', '=', 'at.exam_id')
            ->where('at.status', 'submitted')
            ->when($schoolId, fn ($q) => $q->where('e.school_id', $schoolId))
            ->when($classId, fn ($q) => $q->where('e.class_id', $classId))
            ->when($createdBy, fn ($q) => $q->where('e.created_by', $createdBy))
            ->when($ids !== null, fn ($q) => $q->whereIn('e.class_id', $ids))
            ->selectRaw('(e.topic_id IS NULL) as is_mixed, count(*) submissions, avg(at.score / nullif(at.total_questions,0)) * 100 avg_pct')
            ->groupBy('is_mixed')->get()->keyBy('is_mixed');

        $pick = fn ($mixed) => [
            'exams'       => (int) ($examRows[$mixed]->exams ?? 0),
            'submissions' => (int) ($perfRows[$mixed]->submissions ?? 0),
            'avg'         => isset($perfRows[$mixed]) && $perfRows[$mixed]->avg_pct !== null ? (int) round($perfRows[$mixed]->avg_pct) : null,
        ];

        return ['single' => $pick(0), 'mixed' => $pick(1)];
    }

    private function topicStats($query): array
    {
        return $query
            ->join('v2_questions as q', 'q.id', '=', 'a.question_id')
            ->leftJoin('v2_topics as t', 't.id', '=', 'q.topic_id')
            // Exclude voided exam-questions from every topic stat (single source of
            // truth = the v2_exam_questions pivot). veat resolves the answer's exam.
            ->join('v2_exam_attempts as veat', 'veat.id', '=', 'a.attempt_id')
            ->join('v2_exam_questions as veq', fn ($j) => $j->on('veq.exam_id', '=', 'veat.exam_id')->on('veq.question_id', '=', 'a.question_id'))
            ->where('veq.is_voided', false)
            ->selectRaw('t.external_id, t.title, count(*) as total, sum(a.is_correct) as correct')
            ->groupBy('t.external_id', 't.title')
            ->orderByRaw('CAST(t.external_id AS UNSIGNED)')
            ->get()
            ->map(fn ($r) => [
                'topic'   => $r->title ?? 'Untagged',
                'correct' => (int) $r->correct,
                'total'   => (int) $r->total,
                'percent' => $r->total ? (int) round($r->correct / $r->total * 100) : 0,
            ])
            ->all();
    }
}
