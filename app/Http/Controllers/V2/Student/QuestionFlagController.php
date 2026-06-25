<?php

namespace App\Http\Controllers\V2\Student;

use App\Http\Controllers\Controller;
use App\Models\V2\Exam;
use App\Models\V2\ExamQuestion;
use App\Models\V2\Question;
use App\Models\V2\QuestionFlag;
use App\Services\V2\AuditLogger;
use App\Services\V2\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/*
|--------------------------------------------------------------------------
| Student question flag — "report a problem" during an exam / on the result
|--------------------------------------------------------------------------
| A student flag is a SOFT signal: unlike a teacher flag it does NOT pull the
| question from the pool. It records a pending row (level = student) tied to the
| exam it was raised on and notifies that exam's teacher(s), who make the call
| (dismiss / void for this exam / escalate up the chain).
*/
class QuestionFlagController extends Controller
{
    /** Student-uploaded flag screenshots live here (public disk). */
    private const UPLOAD_DIR = 'v2/question-flags';

    private function student()
    {
        return auth('v2_student')->user();
    }

    public function store(Request $request, Exam $exam, Question $question, NotificationService $notifications)
    {
        $student = $this->student();

        // Must be enrolled in this exam's class, and the question must belong to this paper.
        abort_unless($student->classes()->where('v2_classes.id', $exam->class_id)->exists(), 403);
        abort_unless($exam->examQuestions()->where('question_id', $question->id)->exists(), 404);

        // Once the teacher has acted, the student can't re-open it: a voided question
        // is closed for everyone; a dismissed/voided prior report is closed for this
        // student. (A fresh OPEN report just refreshes via updateOrCreate below.)
        $voided = (bool) ExamQuestion::where('exam_id', $exam->id)->where('question_id', $question->id)->value('is_voided');
        $priorStatus = QuestionFlag::studentLevel()
            ->where('exam_id', $exam->id)->where('question_id', $question->id)
            ->where('flagged_by_student_id', $student->id)
            ->orderByDesc('id')->value('status');

        if ($voided || in_array($priorStatus, ['dismissed', 'escalated', 'voided'], true)) {
            $msg = match (true) {
                $voided                       => 'This question is under review and has been excluded from scoring for this exam.',
                $priorStatus === 'escalated'  => 'Your teacher has escalated this question for quality review.',
                default                       => 'Your teacher has reviewed this report and decided to keep the question. If you still believe there is an issue, please discuss it with your teacher — they or your school administration can escalate it for quality review if needed.',
            };

            return $request->wantsJson()
                ? response()->json(['ok' => false, 'message' => $msg], 200)
                : back()->with('error', $msg);
        }

        $data = $request->validate([
            'reason'     => ['required', Rule::in(array_keys(QuestionFlag::REASONS))],
            'note'       => ['nullable', 'string', 'max:1000'],
            // Optional single image — helps show cropped diagrams / broken rendering / mobile bugs.
            'screenshot' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:3072'],
        ]);

        $path = $request->hasFile('screenshot')
            ? $request->file('screenshot')->store(self::UPLOAD_DIR.'/'.$question->id, 'public')
            : null;

        // One open flag per (student, exam, question) — re-reporting refreshes it
        // (and keeps a freshly attached screenshot rather than duplicating the row).
        QuestionFlag::updateOrCreate(
            [
                'level'                 => 'student',
                'flagged_by_student_id' => $student->id,
                'exam_id'               => $exam->id,
                'question_id'           => $question->id,
                'status'                => 'open',
            ],
            array_filter([
                'school_id'       => $student->school_id,
                'reason'          => $data['reason'],
                'note'            => $data['note'] ?? null,
                'screenshot_path' => $path,
            ], fn ($v) => $v !== null),
        );

        // Soft signal only — the question stays live. The exam's teacher is notified.
        $notifications->notifyTeachersOfStudentFlag($exam, $question->id, $student, $data['reason'], $data['note'] ?? null);

        AuditLogger::record('question.flagged_by_student', $question, [
            'exam_id' => $exam->id,
            'reason'  => $data['reason'],
        ]);

        $message = 'Reported — your teacher will review it. Thanks for flagging it.';

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
