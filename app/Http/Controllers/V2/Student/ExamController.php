<?php

namespace App\Http\Controllers\V2\Student;

use App\Http\Controllers\Controller;
use App\Models\V2\Exam;
use App\Models\V2\ExamAttempt;
use App\Services\V2\AuditLogger;
use App\Services\V2\ExamService;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    private function student()
    {
        return auth('v2_student')->user();
    }

    public function index()
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

        return view('v2.student.exams.index', compact('exams', 'attempts'));
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
        // A submitted result is viewable any time — even after expiry.
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

        return view('v2.student.exams.result', [
            'exam'       => $exam,
            'attempt'    => $attempt,
            'answers'    => $answers,
            'topicStats' => $service->topicStatsForAttempt($attempt),
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
