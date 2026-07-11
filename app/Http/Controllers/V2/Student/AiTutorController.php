<?php

namespace App\Http\Controllers\V2\Student;

use App\Http\Controllers\Controller;
use App\Models\V2\AiTutorChat;
use App\Models\V2\AiTutorMessage;
use App\Models\V2\AiTutorQuiz;
use App\Models\V2\StudentMistake;
use App\Services\AI\StudentTutorContextBuilder;
use App\Services\AI\StudentTutorService;
use App\Services\AI\TutorActionDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
    /**
     * Quiz budget. Enforced HERE (not on the route) because a quiz can be
     * triggered two ways - the "Quiz me" button hits /quiz, and a typed
     * "quiz me" reaches /message - and BOTH paths funnel through
     * quizResponse(). One shared per-student bucket guarantees a typed quiz is
     * always billed to the quiz limiter (5/min, 60/day) and can never ride the
     * more generous chat limiter. See routes/v2.php: the /quiz route no longer
     * carries throttle:ai-quiz for this reason.
     */
    private const QUIZ_PER_MINUTE = 5;
    private const QUIZ_PER_DAY = 60;

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

    /**
     * Send a student message; returns the assistant reply.
     *
     * Server-side intent is the source of truth: an explicit "quiz me" typed
     * into the composer generates a quiz (billed to the quiz limiter) instead
     * of a chat reply - even when the React fast-path is bypassed or this
     * endpoint is POSTed directly. The typed command is persisted as a real
     * user turn (inside generateQuiz) so the timeline stays refresh-stable.
     */
    public function message(AiTutorChat $chat, Request $request, StudentTutorService $tutor, TutorActionDetector $detector)
    {
        $this->authorizeChat($chat);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if ($detector->detect($data['message']) === TutorActionDetector::ACTION_QUIZ) {
            // Reuse the ONE quiz path (limiter + generation + payload shape).
            return $this->quizResponse($chat, $data['message'], $tutor);
        }

        $result = $tutor->sendMessage($chat, $this->student(), $data['message']);

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'error' => 'The tutor is unavailable right now. Please try again.'], 502);
        }

        return response()->json(['ok' => true, 'message' => $this->messageJson($result['message'])]);
    }

    /**
     * Generate a 3-5 question mini quiz from the chat's context. Same endpoint
     * for the "Quiz me" button and a typed quiz command - the optional
     * `message` is the typed command, persisted so the timeline stays
     * refresh-stable. At most one active `ready` quiz per chat (enforced in
     * the service); a request while one exists returns the existing quiz.
     */
    public function quiz(AiTutorChat $chat, Request $request, StudentTutorService $tutor)
    {
        $this->authorizeChat($chat);

        $data = $request->validate([
            'message' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        return $this->quizResponse($chat, $data['message'] ?? null, $tutor);
    }

    /**
     * The single quiz entry point shared by the "Quiz me" button (/quiz) and a
     * typed quiz command that arrives via /message. Enforces the quiz budget,
     * generates (or returns the existing ready quiz), and shapes the payload
     * identically for both callers.
     */
    private function quizResponse(AiTutorChat $chat, ?string $message, StudentTutorService $tutor): JsonResponse
    {
        if ($over = $this->guardQuizBudget()) {
            return $over;
        }

        $result = $tutor->generateQuiz($chat, $this->student(), $message);

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'error' => 'Could not generate a quiz right now. Please try again.'], 502);
        }

        return response()->json(array_filter([
            'ok'       => true,
            'quiz'     => $this->quizJson($result['quiz']),
            // The persisted typed command, so the client can render it above the
            // quiz without a refetch (null for the "Quiz me" button path).
            'message'  => isset($result['message']) && $result['message'] ? $this->messageJson($result['message']) : null,
            'existing' => $result['existing'] ?? false,
        ], fn ($v) => $v !== null));
    }

    /**
     * Per-student quiz rate + daily cost cap, mirroring the old ai-quiz route
     * limiter but applied in code so BOTH the button and the /message-typed
     * path share one bucket. Returns a friendly 429 when over budget, else null
     * (after recording the hit).
     */
    private function guardQuizBudget(): ?JsonResponse
    {
        $actor = v2_actor();
        $key = 'ai-quiz#'.($actor ? $actor['guard'].'#'.$actor['id'] : 'student#'.$this->student()->getAuthIdentifier());

        if (RateLimiter::tooManyAttempts($key.':min', self::QUIZ_PER_MINUTE)) {
            return response()->json(['ok' => false, 'error' => "You're going a little fast - give it a few seconds and try again."], 429);
        }
        if (RateLimiter::tooManyAttempts($key.':day', self::QUIZ_PER_DAY)) {
            return response()->json(['ok' => false, 'error' => "You've reached today's quiz limit - it resets tomorrow. Try reviewing your earlier quizzes."], 429);
        }

        RateLimiter::hit($key.':min', 60);
        RateLimiter::hit($key.':day', 86_400);

        return null;
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

        // Batched Save-to-Notes provenance for every assistant answer (one query).
        $saved = $this->answerProvenance($chat);

        return [
            'ok'   => true,
            'chat' => [
                'id'          => $chat->getRouteKey(),
                'source_type' => $chat->source_type,
                'status'      => $chat->status,
                'title'       => $chat->title,
            ],
            'messages' => $chat->messages->map(fn ($m) => $this->messageJson($m, $saved[$m->id] ?? null))->values(),
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

    private function messageJson(AiTutorMessage $message, ?array $saved = null): array
    {
        return [
            'id'         => $message->id,
            'role'       => $message->role,
            'content'    => $message->content,
            'created_at' => $message->created_at?->toIso8601String(),
            // Provenance (assistant answers only): {count, pages}. Informational.
            'saved'      => $message->role === AiTutorMessage::ROLE_ASSISTANT
                ? ($saved ?? ['count' => 0, 'pages' => []])
                : null,
        ];
    }

    /** @return array<int, array{count:int, pages:array}> keyed by assistant message id */
    private function answerProvenance(AiTutorChat $chat): array
    {
        $sources = $chat->messages
            ->where('role', AiTutorMessage::ROLE_ASSISTANT)
            ->mapWithKeys(fn ($m) => [$m->id => ['source' => 'ai_answer', 'source_id' => $m->id]])
            ->all();

        if ($sources === []) {
            return [];
        }

        return app(\App\Services\V2\NotesProvenanceService::class)->forSources($chat->student_id, $sources);
    }
}
