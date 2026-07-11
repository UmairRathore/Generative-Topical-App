<?php

namespace App\Services\V2;

use App\Models\V2\AiTutorChat;
use App\Models\V2\AiTutorMessage;
use App\Models\V2\AiTutorQuiz;
use App\Models\V2\AiTutorQuizAttempt;
use App\Models\V2\ExamAttempt;
use App\Models\V2\QuestionLearningAsset;
use App\Models\V2\Student;
use App\Models\V2\StudentMistake;
use App\Models\V2\StudentMistakeEvent;
use App\Models\V2\Subject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The Mistake Bank (Learning Hub). Captures every incorrect, non-voided answer
 * as ONE persistent row per (student, question), records history, and serves the
 * release-gated, filtered list + analytics the Learning Hub renders. No AI calls
 * here - only read-only AI *status* aggregates for the hub cards.
 */
class MistakeBankService
{
    /* ============================ Capture ============================ */

    /**
     * Fold an attempt's wrong (non-voided) answers into the Mistake Bank.
     * Idempotent per attempt. Returns the number of mistakes created/updated.
     * Callers MUST wrap this so a failure never breaks exam submission.
     */
    public function syncFromAttempt(ExamAttempt $attempt): int
    {
        if ($attempt->status !== 'submitted') {
            return 0;
        }

        // Raw query (no global scopes) so capture works in any context.
        $wrong = DB::table('v2_exam_answers as a')
            ->join('v2_exam_questions as eq', fn ($j) => $j->on('eq.question_id', '=', 'a.question_id')->where('eq.exam_id', '=', $attempt->exam_id))
            ->join('v2_questions as q', 'q.id', '=', 'a.question_id')
            ->where('a.attempt_id', $attempt->id)
            ->where('a.is_correct', false)
            ->where('eq.is_voided', false)
            ->get(['a.question_id', 'a.selected_option', 'a.correct_option',
                'q.subject_id', 'q.topic_id', 'q.subtopic_id', 'q.difficulty', 'q.year', 'q.source_paper']);

        $recorded = 0;
        foreach ($wrong as $row) {
            $recorded += $this->recordWrong($attempt, $row) ? 1 : 0;
        }

        return $recorded;
    }

    /** Upsert one wrong answer into the bank + append history. Returns false if this attempt was already recorded. */
    protected function recordWrong(ExamAttempt $attempt, object $row): bool
    {
        return DB::transaction(function () use ($attempt, $row) {
            $mistake = StudentMistake::withoutGlobalScopes()
                ->where('student_id', $attempt->student_id)
                ->where('question_id', $row->question_id)
                ->first();

            $reopened = false;

            if (! $mistake) {
                $mistake = new StudentMistake([
                    'student_id'   => $attempt->student_id,
                    'school_id'    => $attempt->school_id,
                    'question_id'  => $row->question_id,
                    'subject_id'   => $row->subject_id,
                    'topic_id'     => $row->topic_id,
                    'subtopic_id'  => $row->subtopic_id,
                    'difficulty'   => $row->difficulty,
                    'year'         => $row->year,
                    'source_paper' => $row->source_paper,
                    'first_wrong_attempt_id'  => $attempt->id,
                    'latest_wrong_attempt_id' => $attempt->id,
                    'latest_exam_id'  => $attempt->exam_id,
                    'selected_option' => $row->selected_option,
                    'correct_option'  => $row->correct_option,
                    'mistake_count'   => 1,
                    'first_wrong_at'  => now(),
                    'last_wrong_at'   => now(),
                    'status'          => StudentMistake::STATUS_NEW,
                ]);
                $mistake->save();
            } else {
                // Idempotency: this attempt already contributed a wrong event -> no double count.
                $already = StudentMistakeEvent::where('student_mistake_id', $mistake->id)
                    ->where('attempt_id', $attempt->id)
                    ->where('event_type', StudentMistakeEvent::WRONG)
                    ->exists();
                if ($already) {
                    return false;
                }

                $mistake->mistake_count += 1;
                $mistake->last_wrong_at = now();
                $mistake->latest_wrong_attempt_id = $attempt->id;
                $mistake->latest_exam_id = $attempt->exam_id;
                $mistake->selected_option = $row->selected_option;
                $mistake->correct_option = $row->correct_option;

                // Got a resolved question wrong again -> resurface it.
                if (in_array($mistake->status, StudentMistake::RESOLVED_STATUSES, true)) {
                    $mistake->status = StudentMistake::STATUS_NEW;
                    $mistake->resolved_at = null;
                    $mistake->archived_at = null;
                    $mistake->mastered_at = null;
                    $mistake->confidence = null;
                    $reopened = true;
                }
                $mistake->save();
            }

            $this->event($mistake, StudentMistakeEvent::WRONG, [
                'attempt_id'      => $attempt->id,
                'exam_id'         => $attempt->exam_id,
                'selected_option' => $row->selected_option,
                'correct_option'  => $row->correct_option,
            ]);

            if ($reopened) {
                $this->event($mistake, StudentMistakeEvent::REOPENED, [
                    'attempt_id' => $attempt->id,
                    'exam_id'    => $attempt->exam_id,
                ]);
            }

            return true;
        });
    }

    /* ============================ Listing ============================ */

    /**
     * Release-gated, filtered, sorted, paginated mistakes for a student.
     * Default ordering groups by subject -> topic (headers rendered in the view).
     *
     * @param  array{filter?:string,subject_id?:int,topic_id?:int,difficulty?:string,hide_completed?:bool}  $filters
     */
    public function list(Student $student, array $filters = [], string $sort = 'grouped', int $perPage = 20): LengthAwarePaginator
    {
        $q = $this->releasedQuery($student)
            ->with([
                // ANTI-SCRAPING: the list is summary-first. Only a ~120-char
                // slice of the stem ever leaves the database - the full
                // question_text must NEVER be selected here, so even a future
                // JSON/Inertia conversion of this list cannot expose stems.
                // (Blade truncates further to 100 visible chars; a slice that
                // ends inside an HTML tag just yields a shorter teaser.)
                'question' => fn ($qq) => $qq->select(
                    'id',
                    DB::raw('SUBSTR(question_text, 1, 120) as question_text'),
                    DB::raw('SUBSTR(text_before, 1, 120) as text_before'),
                ),
                'subject:id,name', 'topic:id,external_id,title', 'subtopic:id,title',
                'latestExam:id,title',
            ]);

        $this->addAiSummarySelects($q);
        $this->applyFilters($q, $filters);
        $this->applySort($q, $sort);

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * Per-row AI Tutor aggregates for the hub cards (status only - never chat
     * content). Correlated subselects, so no N+1 at page size 20.
     */
    protected function addAiSummarySelects(Builder $q): void
    {
        $chatIds = fn () => AiTutorChat::query()->select('id')
            ->whereColumn('v2_ai_tutor_chats.student_mistake_id', 'v2_student_mistakes.id');
        $quizIds = fn () => AiTutorQuiz::query()->select('id')
            ->whereColumn('v2_ai_tutor_quizzes.student_mistake_id', 'v2_student_mistakes.id');

        $q->addSelect([
            'ai_message_count'      => AiTutorMessage::selectRaw('COUNT(*)')->whereIn('chat_id', $chatIds()),
            'ai_last_message_at'    => AiTutorMessage::select('created_at')->whereIn('chat_id', $chatIds())->orderByDesc('id')->limit(1),
            'ai_quiz_attempt_count' => AiTutorQuizAttempt::selectRaw('COUNT(*)')->whereIn('quiz_id', $quizIds()),
            'ai_latest_quiz_score'  => AiTutorQuizAttempt::select('score')->whereIn('quiz_id', $quizIds())->orderByDesc('id')->limit(1),
            'ai_latest_quiz_total'  => AiTutorQuizAttempt::select('total')->whereIn('quiz_id', $quizIds())->orderByDesc('id')->limit(1),
            'ai_best_quiz_score'    => AiTutorQuizAttempt::select('score')->whereIn('quiz_id', $quizIds())->orderByDesc('score')->orderByDesc('id')->limit(1),
            'ai_best_quiz_total'    => AiTutorQuizAttempt::select('total')->whereIn('quiz_id', $quizIds())->orderByDesc('score')->orderByDesc('id')->limit(1),
        ]);
    }

    /** Base query: this student's mistakes whose latest source exam has released results. */
    protected function releasedQuery(Student $student): Builder
    {
        return StudentMistake::query()
            ->where('student_id', $student->id)
            ->whereExists(fn ($sub) => $sub->selectRaw('1')->from('v2_exams')
                ->whereColumn('v2_exams.id', 'v2_student_mistakes.latest_exam_id')
                ->whereNotNull('v2_exams.results_released_at'));
    }

    protected function applyFilters(Builder $q, array $filters): void
    {
        if (! empty($filters['hide_completed'])) {
            $q->whereNotIn('status', StudentMistake::RESOLVED_STATUSES);
        }

        switch ($filters['filter'] ?? 'all') {
            case 'needs_review':   $q->whereNotIn('status', StudentMistake::RESOLVED_STATUSES); break;
            case 'most_repeated':  $q->where('mistake_count', '>=', 2); break;
            case 'never_reviewed': $q->where('review_count', 0); break;
            case 'mastered':       $q->where('status', StudentMistake::STATUS_MASTERED); break;

            // AI Tutor engagement filters (status only - EXISTS subqueries on
            // the AI tables, no chat content touched).
            case 'ai_not_discussed':
                $q->whereDoesntHave('aiChats', fn ($c) => $c->has('messages'));
                break;
            case 'ai_discussed':
                $q->whereHas('aiChats', fn ($c) => $c->has('messages'));
                break;
            case 'ai_has_quiz':
                $q->whereHas('aiQuizzes', fn ($z) => $z->has('attempts'));
                break;
            case 'ai_low_quiz':
                // Any attempt scoring under half marks.
                $q->whereHas('aiQuizzes.attempts', fn ($a) => $a->whereColumn('score', '<', DB::raw('total / 2')));
                break;

            case 'all': default:   break;
        }

        if (! empty($filters['subject_id']))  { $q->where('subject_id', (int) $filters['subject_id']); }
        if (! empty($filters['topic_id']))    { $q->where('topic_id', (int) $filters['topic_id']); }
        if (! empty($filters['difficulty']))  { $q->where('difficulty', $filters['difficulty']); }
    }

    protected function applySort(Builder $q, string $sort): void
    {
        switch ($sort) {
            case 'recent':        $q->orderByDesc('last_wrong_at'); break;
            case 'oldest':        $q->orderBy('last_wrong_at'); break;
            case 'most_mistakes': $q->orderByDesc('mistake_count')->orderByDesc('last_wrong_at'); break;
            case 'difficulty':    $q->orderByRaw("CASE difficulty WHEN 'hard' THEN 0 WHEN 'medium' THEN 1 WHEN 'easy' THEN 2 ELSE 3 END")->orderByDesc('last_wrong_at'); break;
            case 'grouped': default:
                // Subject -> topic grouping (view renders section headers), newest first within.
                $q->orderBy('subject_id')->orderBy('topic_id')->orderByDesc('last_wrong_at');
                break;
        }
    }

    /** Subjects the student actually has (released) mistakes in - for the filter dropdown. */
    public function subjectOptions(Student $student): \Illuminate\Support\Collection
    {
        $ids = $this->releasedQuery($student)->distinct()->pluck('subject_id');

        return Subject::whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Topics (that actually have released mistakes) grouped by subject id - powers the
     * subject-dependent topic dropdown, which stays disabled until a subject is chosen.
     *
     * @return array<int, array<int, array{id:int, title:string}>>
     */
    public function topicOptions(Student $student): array
    {
        return $this->releasedQuery($student)
            ->whereNotNull('topic_id')
            ->join('v2_topics as t', 't.id', '=', 'v2_student_mistakes.topic_id')
            ->distinct()
            ->orderBy('t.title')
            ->get(['v2_student_mistakes.subject_id as subject_id', 't.id as topic_id', 't.title as title'])
            ->groupBy('subject_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => ['id' => (int) $r->topic_id, 'title' => $r->title])->values()->all())
            ->toArray();
    }

    /* ============================ Analytics ============================ */

    /** Headline numbers for the hub (all release-gated). */
    public function analytics(Student $student): array
    {
        $base = fn () => $this->releasedQuery($student);

        $total       = (clone $base())->count();
        $mastered    = (clone $base())->where('status', StudentMistake::STATUS_MASTERED)->count();
        $unresolved  = (clone $base())->whereNotIn('status', StudentMistake::RESOLVED_STATUSES)->count();
        // Captured but not yet surfaced because their exam's results aren't released.
        $pending     = max(0, StudentMistake::where('student_id', $student->id)->count() - $total);

        $topWeakTopics = (clone $base())
            ->whereNotNull('topic_id')
            ->join('v2_topics as t', 't.id', '=', 'v2_student_mistakes.topic_id')
            ->groupBy('v2_student_mistakes.topic_id', 't.title')
            ->orderByRaw('SUM(mistake_count) DESC')
            ->limit(5)
            ->get([
                't.title as topic',
                DB::raw('COUNT(*) as questions'),
                DB::raw('SUM(mistake_count) as attempts'),
            ]);

        // Count only - never load question text into the analytics card.
        $mostRepeated = (clone $base())
            ->orderByDesc('mistake_count')
            ->first();

        return [
            'total'         => $total,
            'unresolved'    => $unresolved,
            'mastered'      => $mastered,
            'pending'       => $pending,
            'top_topics'    => $topWeakTopics,
            'most_repeated' => $mostRepeated,
        ];
    }

    /* ============================ Transitions ============================ */

    public function markReviewed(StudentMistake $mistake): void
    {
        $mistake->review_count += 1;
        $mistake->last_reviewed_at = now();
        if ($mistake->status === StudentMistake::STATUS_NEW) {
            $mistake->status = StudentMistake::STATUS_REVIEWED;
        }
        $mistake->save();
        $this->event($mistake, StudentMistakeEvent::REVIEWED);
    }

    public function markMastered(StudentMistake $mistake): void
    {
        $mistake->status = StudentMistake::STATUS_MASTERED;
        $mistake->mastered_at = now();
        $mistake->resolved_at = now();
        $mistake->save();
        $this->event($mistake, StudentMistakeEvent::MARKED_MASTERED);
    }

    /** Reset a resolved mistake back into active revision. */
    public function reopen(StudentMistake $mistake): void
    {
        $mistake->status = StudentMistake::STATUS_NEW;
        $mistake->mastered_at = null;
        $mistake->resolved_at = null;
        $mistake->archived_at = null;
        $mistake->save();
        $this->event($mistake, StudentMistakeEvent::REOPENED);
    }

    public function archive(StudentMistake $mistake): void
    {
        $mistake->status = StudentMistake::STATUS_ARCHIVED;
        $mistake->archived_at = now();
        $mistake->resolved_at = now();
        $mistake->save();
    }

    /* ============================ Learning assets (display-only) ============================ */

    /** The visible (generated/reviewed/approved) asset of a type for a question, or null. */
    public function visibleAsset(int $questionId, string $type): ?QuestionLearningAsset
    {
        if (! in_array($type, QuestionLearningAsset::ALLOWED_TYPES, true)) {
            return null;
        }

        return QuestionLearningAsset::where('question_id', $questionId)
            ->where('asset_type', $type)
            ->visible()
            ->orderByRaw("CASE status WHEN 'approved' THEN 0 WHEN 'reviewed' THEN 1 ELSE 2 END")
            ->first();
    }

    public function recordAssetViewed(StudentMistake $mistake, string $type): void
    {
        $this->event($mistake, StudentMistakeEvent::ASSET_VIEWED, ['meta_json' => ['asset_type' => $type]]);
    }

    /* ============================ helpers ============================ */

    protected function event(StudentMistake $mistake, string $type, array $extra = []): void
    {
        StudentMistakeEvent::create(array_merge([
            'student_mistake_id' => $mistake->id,
            'student_id'         => $mistake->student_id,
            'question_id'        => $mistake->question_id,
            'event_type'         => $type,
            'occurred_at'        => now(),
        ], $extra));
    }
}
