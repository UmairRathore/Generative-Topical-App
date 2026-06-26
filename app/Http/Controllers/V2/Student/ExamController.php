<?php

namespace App\Http\Controllers\V2\Student;

use App\Http\Controllers\Controller;
use App\Models\V2\Exam;
use App\Models\V2\ExamAttempt;
use App\Models\V2\QuestionFlag;
use App\Services\V2\AuditLogger;
use App\Services\V2\ExamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    private function student()
    {
        return auth('v2_student')->user();
    }

    public function index(ExamService $service)
    {
        $student  = $this->student();
        $classIds = $student->classes()->wherePivot('status', 'active')->pluck('v2_classes.id');

        $exams = Exam::published()
            ->whereIn('class_id', $classIds)
            ->with(['subject', 'topic', 'schoolClass.grade'])
            ->latest()
            ->get();

        $attempts = ExamAttempt::where('student_id', $student->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->get()
            ->keyBy('exam_id');

        // Distinct topic titles per exam - drives the "view topics" modal (Mixed exams list several).
        $topicMap = DB::table('v2_exam_questions as eq')
            ->join('v2_questions as q', 'q.id', '=', 'eq.question_id')
            ->join('v2_topics as t', 't.id', '=', 'q.topic_id')
            ->whereIn('eq.exam_id', $exams->pluck('id'))
            ->where('eq.is_voided', false)
            ->select('eq.exam_id', 't.title')
            ->distinct()
            ->get()
            ->groupBy('exam_id')
            ->map(fn ($rows) => $rows->pluck('title')->sort(SORT_NATURAL)->values()->all());

        // Per-exam, per-topic performance for the student - only for exams that are both
        // submitted AND released (so unreleased scores never leak). Other exams fall back to
        // a plain topic list (coverage only).
        $releasedExamIds = $exams->filter(function ($e) use ($attempts) {
            $att = $attempts[$e->id] ?? null;

            return $att && $att->status === 'submitted' && $e->resultsReleased();
        })->pluck('id');

        $perExamTopic = collect();
        if ($releasedExamIds->isNotEmpty()) {
            $perExamTopic = DB::table('v2_exam_answers as a')
                ->join('v2_exam_attempts as at', 'at.id', '=', 'a.attempt_id')
                ->join('v2_questions as q', 'q.id', '=', 'a.question_id')
                ->join('v2_topics as t', 't.id', '=', 'q.topic_id')
                ->join('v2_exam_questions as veq', fn ($j) => $j->on('veq.exam_id', '=', 'at.exam_id')->on('veq.question_id', '=', 'a.question_id'))
                ->where('veq.is_voided', false)
                ->where('at.student_id', $student->id)
                ->where('at.status', 'submitted')
                ->whereIn('at.exam_id', $releasedExamIds)
                ->selectRaw('at.exam_id, t.title, count(*) total, sum(a.is_correct) correct')
                ->groupBy('at.exam_id', 't.title')
                ->get()
                ->groupBy('exam_id');
        }

        // Topic payload per exam for the row modal: scored bars where released, else names only.
        $examTopics = [];
        foreach ($exams as $e) {
            if ($perExamTopic->has($e->id)) {
                $examTopics[$e->id] = $perExamTopic->get($e->id)
                    ->map(fn ($r) => [
                        'topic'     => $r->title,
                        'attempted' => true,
                        'correct'   => (int) $r->correct,
                        'total'     => (int) $r->total,
                        'percent'   => $r->total ? (int) round($r->correct / $r->total * 100) : 0,
                    ])
                    ->sortBy('topic')->values()->all();
            } else {
                $examTopics[$e->id] = collect($topicMap[$e->id] ?? [])
                    ->map(fn ($title) => ['topic' => $title, 'attempted' => false, 'correct' => 0, 'total' => 0, 'percent' => 0])
                    ->all();
            }
        }

        // One table per subject, subjects A-Z.
        $examsBySubject = $exams->groupBy(fn ($e) => $e->subject?->name ?? 'Other')
            ->sortKeys();

        // Per-subject performance (attempted / missed / avg / topic breakdown) - reuse the
        // dashboard computation so the container headers + topics modal stay consistent.
        $subjectStats = collect($service->studentDashboard($student)['subjects'])->keyBy('subject');

        return view('v2.student.exams.index', compact('examsBySubject', 'attempts', 'topicMap', 'subjectStats', 'examTopics'));
    }

    public function take(Exam $exam, ExamService $service)
    {
        $student = $this->student();
        $this->ensureCanTake($exam, $student);

        $attempt = $service->startAttempt($exam, $student);
        if ($attempt->isSubmitted()) {
            return redirect()->route('v2.student.exams.result', $exam);
        }

        $exam->load(['examQuestions.questionVersion', 'examQuestions.question.options', 'examQuestions.question.images', 'topic', 'subject']);
        $exam->renderFrozenQuestions();

        // Chemistry exams get a Periodic Table reference panel (not question content).
        $ptPath = config('v2.periodic_table');
        $periodicTable = in_array($exam->subject?->code, config('v2.periodic_table_codes', []), true)
            ? '/storage/'.$ptPath.'?v='.(@filemtime(public_path('storage/'.$ptPath)) ?: 1)
            : null;

        return view('v2.student.exams.take', compact('exam', 'attempt', 'periodicTable'));
    }

    public function submit(Exam $exam, Request $request, ExamService $service)
    {
        $student = $this->student();
        $this->ensureCanTake($exam, $student);

        $attempt = $service->startAttempt($exam, $student);
        if (! $attempt->isSubmitted()) {
            $attempt = $service->submit($attempt, (array) $request->input('answers', []));
            AuditLogger::record('exam.submitted', $exam, ['student_id' => $student->id, 'score' => $attempt->score]);
        }

        return redirect()->route('v2.student.exams.result', $exam)->with('success', 'Your test has been submitted.');
    }

    public function result(Exam $exam, ExamService $service)
    {
        $student = $this->student();
        // A submitted result is viewable any time - even after expiry.
        abort_unless($student->classes()->where('v2_classes.id', $exam->class_id)->exists(), 403);

        $attempt = ExamAttempt::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->where('status', 'submitted')
            ->first();

        if (! $attempt) {
            return redirect()->route('v2.student.exams.take', $exam);
        }

        // Score + answers are hidden until the teacher releases results.
        if (! $exam->resultsReleased()) {
            return view('v2.student.exams.result_pending', ['exam' => $exam, 'attempt' => $attempt]);
        }

        $exam->load(['examQuestions.questionVersion', 'examQuestions.question.options', 'examQuestions.question.images', 'topic']);
        $exam->renderFrozenQuestions();
        $answers = $attempt->answers()->get()->keyBy('question_id');
        $attempt->setRelation('answers', $answers);

        // Questions this student flagged - drives the amber palette state.
        $flaggedQids = QuestionFlag::studentLevel()
            ->where('exam_id', $exam->id)
            ->where('flagged_by_student_id', $student->id)
            ->pluck('question_id')->all();

        $result = $service->resultBreakdown($exam, $attempt, $flaggedQids);

        return view('v2.student.exams.result', [
            'exam'          => $exam,
            'attempt'       => $attempt,
            'answers'       => $answers,
            'topicStats'    => $service->topicStatsForAttempt($attempt),
            'breakdown'     => $result['breakdown'],
            'palette'       => $result['palette'],
            'revealCorrect' => $exam->resultsReleased(),
        ]);
    }

    /**
     * Same-school is enforced by the model's global scope. A student may TAKE an
     * exam only while it is live (released + inside [available_from,
     * available_until]); once expired it is locked. An already-submitted attempt
     * still resolves (take() redirects it to the result).
     */
    private function ensureCanTake(Exam $exam, $student): void
    {
        abort_unless($student->classes()->where('v2_classes.id', $exam->class_id)->exists(), 403);

        $alreadySubmitted = ExamAttempt::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->where('status', 'submitted')
            ->exists();

        abort_unless($exam->isLive() || $alreadySubmitted, 404, 'This test is not currently open.');
    }
}
