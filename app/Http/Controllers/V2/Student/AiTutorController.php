<?php

namespace App\Http\Controllers\V2\Student;

use App\Http\Controllers\Controller;
use App\Models\V2\AiTutorChat;
use App\Models\V2\AiTutorMessage;
use App\Models\V2\AiTutorQuiz;
use App\Models\V2\StudentMistake;
use App\Services\AI\StudentTutorContextBuilder;
use App\Services\AI\StudentTutorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Student AI Tutor - thin JSON controller; all orchestration lives in
 * StudentTutorService. Every endpoint re-checks ownership AND the result
 * release gate through the chat's anchoring mistake (rules 2 + 12); the
 * school global scope on AiTutorChat/AiTutorQuiz already 404s cross-school
 * probes at route binding.
 */
class AiTutorController extends Controller
{
    private function student()
    {
        return auth('v2_student')->user();
    }

    /** Open (or resume) the chat for this mistake + source type. */
    public function open(
        StudentMistake $mistake,
        Request $request,
        StudentTutorService $tutor,
        StudentTutorContextBuilder $context,
    ) {
        $this->authorizeMistake($mistake);

        $data = $request->validate([
            'source_type' => ['required', Rule::in(AiTutorChat::ACTIVE_SOURCES)],
        ]);

        // Never open an ungrounded chat: without a known correct answer the
        // tutor cannot stay grounded (docs rule 1).
        if (! $context->hasGrounding($mistake)) {
            return response()->json(['ok' => false, 'error' => 'no_grounding'], 422);
        }

        $chat = $tutor->openChat($mistake, $this->student(), $data['source_type']);

        return response()->json($this->chatPayload($chat));
    }

    /** Chat + full history (refresh / deep link). */
    public function show(AiTutorChat $chat)
    {
        $this->authorizeChat($chat);

        return response()->json($this->chatPayload($chat));
    }

    /** Send a student message; returns the assistant reply. */
    public function message(AiTutorChat $chat, Request $request, StudentTutorService $tutor)
    {
        $this->authorizeChat($chat);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $result = $tutor->sendMessage($chat, $this->student(), $data['message']);

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'error' => 'The tutor is unavailable right now. Please try again.'], 502);
        }

        return response()->json(['ok' => true, 'message' => $this->messageJson($result['message'])]);
    }

    /** Generate a 3-5 question mini quiz from the chat's context. */
    public function quiz(AiTutorChat $chat, StudentTutorService $tutor)
    {
        $this->authorizeChat($chat);

        $result = $tutor->generateQuiz($chat, $this->student());

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'error' => 'Could not generate a quiz right now. Please try again.'], 502);
        }

        return response()->json([
            'ok'   => true,
            'quiz' => $this->quizJson($result['quiz']),
        ]);
    }

    /** Score a quiz attempt. Learning signal only - never touches exam results. */
    public function quizAttempt(AiTutorQuiz $quiz, Request $request, StudentTutorService $tutor)
    {
        abort_unless($quiz->student_id === $this->student()->id, 403);

        $chat = $quiz->chat;
        abort_unless($chat && $chat->mistake, 404);
        $this->authorizeMistake($chat->mistake);

        abort_if($quiz->status === AiTutorQuiz::STATUS_ATTEMPTED, 409, 'This quiz has already been attempted.');

        $data = $request->validate([
            'answers'   => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'string', 'max:5'],
        ]);

        // Every quiz question must be answered - and nothing else.
        $expected = $quiz->questions->pluck('id')->sort()->values()->all();
        $given = collect(array_keys($data['answers']))->map(fn ($k) => (int) $k)->sort()->values()->all();
        abort_unless($expected === $given, 422, 'Answers must cover exactly the quiz questions.');

        $attempt = $tutor->submitQuizAttempt($quiz, $this->student(), $data['answers']);

        return response()->json([
            'ok'      => true,
            'score'   => $attempt->score,
            'total'   => $attempt->total,
            'results' => $quiz->questions->map(fn ($q) => [
                'id'             => $q->id,
                'your'           => $data['answers'][$q->id] ?? null,
                'correct'        => strtoupper((string) ($data['answers'][$q->id] ?? '')) === strtoupper($q->correct_option),
                'correct_option' => $q->correct_option,
                'explanation'    => $q->explanation,
            ])->values(),
        ]);
    }

    /** A student may only ever touch their own, released mistake (rules 2 + 12). */
    private function authorizeMistake(StudentMistake $mistake): void
    {
        abort_unless($mistake->student_id === $this->student()->id, 403);
        abort_unless($mistake->latestExam && $mistake->latestExam->resultsReleased(), 404);
    }

    /** All V1 chats are mistake-anchored; the release gate is re-checked every request. */
    private function authorizeChat(AiTutorChat $chat): void
    {
        abort_unless($chat->student_id === $this->student()->id, 403);
        abort_unless($chat->mistake, 404);
        $this->authorizeMistake($chat->mistake);
    }

    private function chatPayload(AiTutorChat $chat): array
    {
        // EVERY quiz travels with the chat: attempted ones render as collapsed
        // score bars in the thread; an unattempted one restores the
        // "finish the quiz before continuing" gate after a refresh.
        $quizzes = $chat->quizzes()->with('questions')->orderBy('id')->get()
            ->map(fn ($q) => $this->quizJson($q))->values();

        return [
            'ok'   => true,
            'chat' => [
                'id'          => $chat->getRouteKey(),
                'source_type' => $chat->source_type,
                'status'      => $chat->status,
                'title'       => $chat->title,
            ],
            'messages' => $chat->messages->map(fn ($m) => $this->messageJson($m))->values(),
            'quizzes'  => $quizzes,
            'urls'     => [
                'show'    => route('v2.student.tutor.chats.show', $chat),
                'message' => route('v2.student.tutor.chats.message', $chat),
                'quiz'    => route('v2.student.tutor.chats.quiz', $chat),
            ],
        ];
    }

    /**
     * Client quiz shape. Answers/explanations are withheld while the quiz is
     * unattempted; once attempted they ship so the thread can show the
     * reviewed state after a refresh.
     */
    private function quizJson(AiTutorQuiz $quiz): array
    {
        $attempt = $quiz->status === AiTutorQuiz::STATUS_ATTEMPTED
            ? $quiz->attempts()->latest('id')->first()
            : null;

        return [
            'id'         => $quiz->getRouteKey(),
            'title'      => $quiz->title,
            'status'     => $quiz->status,
            'created_at' => $quiz->created_at?->toIso8601String(),
            'questions'  => $quiz->questions->map(fn ($q) => array_merge([
                'id'      => $q->id,
                'stem'    => $q->stem,
                'options' => $q->options,
            ], $attempt ? [
                'correct_option' => $q->correct_option,
                'explanation'    => $q->explanation,
            ] : []))->values(),
            'attemptUrl' => route('v2.student.tutor.quizzes.attempt', $quiz),
            'result'     => $attempt ? [
                'score'   => $attempt->score,
                'total'   => $attempt->total,
                'answers' => $attempt->answers,
            ] : null,
        ];
    }

    private function messageJson(AiTutorMessage $message): array
    {
        return [
            'id'         => $message->id,
            'role'       => $message->role,
            'content'    => $message->content,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
