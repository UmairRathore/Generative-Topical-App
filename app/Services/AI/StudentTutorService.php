<?php

namespace App\Services\AI;

use App\Models\V2\AiInteractionLog;
use App\Models\V2\AiTutorChat;
use App\Models\V2\AiTutorContextItem;
use App\Models\V2\AiTutorMessage;
use App\Models\V2\AiTutorQuiz;
use App\Models\V2\AiTutorQuizAttempt;
use App\Models\V2\Student;
use App\Models\V2\StudentMistake;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orchestrates the Student AI Tutor: chats, messages, mini quizzes and the
 * interaction log. Callers (AiTutorController) authorize the mistake/chat
 * BEFORE any method here runs; this service never re-implements tenancy.
 *
 * All official records stay untouched: the tutor writes only to the
 * v2_ai_tutor_* tables and v2_ai_interaction_logs.
 */
class StudentTutorService
{
    /** How many prior turns travel with each request. */
    private const HISTORY_TURNS = 10;

    /** Cap per history turn (chars) - keeps long assistant answers from compounding. */
    private const HISTORY_TURN_CHARS = 1500;

    public function __construct(
        private TopicalAiClient $client,
        private StudentTutorContextBuilder $context,
        private AiInteractionLogger $logger,
    ) {
    }

    /**
     * AI status summary for one mistake (review page card). Aggregates only -
     * never chat content.
     *
     * @return array{message_count:int,last_message_at:?string,latest_quiz_score:?int,latest_quiz_total:?int}
     */
    public function mistakeAiSummary(StudentMistake $mistake): array
    {
        $chatIds = AiTutorChat::where('student_mistake_id', $mistake->id)->pluck('id');

        $latestAttempt = \App\Models\V2\AiTutorQuizAttempt::query()
            ->whereIn('quiz_id', AiTutorQuiz::where('student_mistake_id', $mistake->id)->select('id'))
            ->orderByDesc('id')
            ->first(['score', 'total']);

        return [
            'message_count'      => $chatIds->isEmpty() ? 0 : AiTutorMessage::whereIn('chat_id', $chatIds)->count(),
            'last_message_at'    => $chatIds->isEmpty() ? null : AiTutorMessage::whereIn('chat_id', $chatIds)->max('created_at'),
            'latest_quiz_score'  => $latestAttempt?->score,
            'latest_quiz_total'  => $latestAttempt?->total,
        ];
    }

    /** One active chat per (student, mistake, source_type). */
    public function openChat(StudentMistake $mistake, Student $student, string $sourceType): AiTutorChat
    {
        $mistake->loadMissing(['topic:id,title', 'subject:id,name']);

        return AiTutorChat::firstOrCreate([
            'student_id'         => $student->id,
            'student_mistake_id' => $mistake->id,
            'source_type'        => $sourceType,
            'status'             => AiTutorChat::STATUS_ACTIVE,
        ], [
            'school_id'   => $student->school_id,
            'question_id' => $mistake->question_id,
            'subject_id'  => $mistake->subject_id,
            'topic_id'    => $mistake->topic_id,
            'subtopic_id' => $mistake->subtopic_id,
            'title'       => $mistake->topic?->title ?: $mistake->subject?->name,
        ]);
    }

    /**
     * Send a student message; returns the assistant reply or ok=false on failure.
     * The user message is persisted either way so a retry can re-send it.
     *
     * @return array{ok: bool, message?: AiTutorMessage, error?: string}
     */
    public function sendMessage(AiTutorChat $chat, Student $student, string $text): array
    {
        $mistake = $chat->mistake;

        $userMessage = $chat->messages()->create([
            'role'    => AiTutorMessage::ROLE_USER,
            'content' => $text,
        ]);

        $history = $chat->messages()
            ->where('id', '<', $userMessage->id)
            ->orderByDesc('id')->limit(self::HISTORY_TURNS)->get()
            ->reverse()->values()
            ->map(fn ($m) => ['role' => $m->role, 'content' => Str::limit($m->content, self::HISTORY_TURN_CHARS, '…')])
            ->all();

        $t0 = microtime(true);

        try {
            // Recent performance stats ride along on the first turn only -
            // later turns skip those queries and keep the prompt lean/stable.
            $built = $this->context->build($mistake, $student, $chat->source_type, includeStats: $history === []);

            $response = $this->client->tutorChat([
                'chat_ref' => $chat->getRouteKey(),
                'context'  => $built['payload'],
                'history'  => $history,
                'message'  => $text,
            ]);

            $meta = $response['meta'] ?? [];

            $assistant = $chat->messages()->create([
                'role'              => AiTutorMessage::ROLE_ASSISTANT,
                'content'           => (string) ($response['answer'] ?? ''),
                'model'             => $meta['model'] ?? null,
                'prompt_tokens'     => $meta['input_tokens'] ?? null,
                'completion_tokens' => $meta['output_tokens'] ?? null,
            ]);

            $this->storeContextItems($chat, $assistant, $built['manifest']);
            $chat->update(['last_message_at' => now()]);

            $this->logInteraction('tutor_chat', $chat, AiInteractionLog::STATUS_OK, [
                'provider'          => $meta['provider'] ?? null,
                'model'             => $meta['model'] ?? null,
                'prompt_tokens'     => $meta['input_tokens'] ?? null,
                'completion_tokens' => $meta['output_tokens'] ?? null,
                'total_tokens'      => $meta['total_tokens'] ?? null,
                'cost_usd'          => $meta['cost_usd'] ?? null,
                'latency_ms'        => $meta['latency_ms'] ?? $this->elapsedMs($t0),
                'request_json'      => $this->redactedRequest($chat, $text, count($history), $built['payload']),
                'response_json'     => ['answer' => Str::limit((string) ($response['answer'] ?? ''), 8000), 'meta' => $meta],
            ]);

            return ['ok' => true, 'message' => $assistant];
        } catch (\Throwable $e) {
            Log::warning('AI tutor chat failed: '.$e->getMessage());

            $this->logInteraction('tutor_chat', $chat, AiInteractionLog::STATUS_ERROR, [
                'latency_ms'   => $this->elapsedMs($t0),
                'error'        => Str::limit($e->getMessage(), 1000),
                'request_json' => $this->redactedRequest($chat, $text, count($history)),
            ]);

            return ['ok' => false, 'error' => 'tutor_unavailable'];
        }
    }

    /**
     * Generate a 3-5 question mini quiz from the chat's context.
     *
     * DOMAIN INVARIANT: a chat has at most ONE active `ready` quiz. If one
     * already exists, return it - never create a second quiz or duplicate
     * questions. `$userText` (a typed "quiz me" command) is persisted as a
     * user turn so the timeline stays refresh-stable.
     *
     * @return array{ok: bool, quiz?: AiTutorQuiz, message?: AiTutorMessage, existing?: bool, error?: string}
     */
    public function generateQuiz(AiTutorChat $chat, Student $student, ?string $userText = null): array
    {
        // One-active-quiz guard (server-side; the client gate is belt-and-braces).
        $existing = $chat->quizzes()->where('status', AiTutorQuiz::STATUS_READY)->latest('id')->first();
        if ($existing) {
            return ['ok' => true, 'quiz' => $existing->load('questions'), 'existing' => true];
        }

        $mistake = $chat->mistake;
        $t0 = microtime(true);

        // Persist the typed command as a user turn BEFORE generating, so it
        // survives refresh and reads naturally above the quiz (mirrors how
        // sendMessage keeps the user message even if the AI call then fails).
        $userMessage = ($userText !== null && trim($userText) !== '')
            ? $chat->messages()->create(['role' => AiTutorMessage::ROLE_USER, 'content' => $userText])
            : null;

        try {
            // Quiz questions ground on the question itself - stats add nothing.
            $built = $this->context->build($mistake, $student, $chat->source_type, includeStats: false);

            $response = $this->client->tutorQuiz([
                'chat_ref'      => $chat->getRouteKey(),
                'context'       => $built['payload'],
                'num_questions' => 4,
            ]);

            $meta = $response['meta'] ?? [];
            $questions = $this->validateQuiz($response['quiz'] ?? []);

            $quiz = DB::transaction(function () use ($chat, $student, $mistake, $response, $meta, $questions) {
                $quiz = AiTutorQuiz::create([
                    'chat_id'            => $chat->id,
                    'student_id'         => $student->id,
                    'school_id'          => $student->school_id,
                    'question_id'        => $chat->question_id,
                    'student_mistake_id' => $mistake->id,
                    'title'              => Str::limit((string) ($response['quiz']['title'] ?? 'Quick check'), 180, ''),
                    'status'             => AiTutorQuiz::STATUS_READY,
                    'model'              => $meta['model'] ?? null,
                ]);

                foreach ($questions as $i => $qq) {
                    $quiz->questions()->create([
                        'sort_order'     => $i,
                        'stem'           => $qq['stem'],
                        'options'        => $qq['options'],
                        'correct_option' => $qq['correct_option'],
                        'explanation'    => $qq['explanation'] ?? null,
                    ]);
                }

                return $quiz;
            });

            $this->logInteraction('tutor_quiz', $chat, AiInteractionLog::STATUS_OK, [
                'provider'          => $meta['provider'] ?? null,
                'model'             => $meta['model'] ?? null,
                'prompt_tokens'     => $meta['input_tokens'] ?? null,
                'completion_tokens' => $meta['output_tokens'] ?? null,
                'total_tokens'      => $meta['total_tokens'] ?? null,
                'cost_usd'          => $meta['cost_usd'] ?? null,
                'latency_ms'        => $meta['latency_ms'] ?? $this->elapsedMs($t0),
                'request_json'      => $this->redactedRequest($chat, '[quiz]', 0, $built['payload']),
                'response_json'     => ['quiz_title' => $quiz->title, 'question_count' => count($questions), 'meta' => $meta],
            ]);

            if ($userMessage) {
                $chat->update(['last_message_at' => now()]);
            }

            return ['ok' => true, 'quiz' => $quiz->load('questions'), 'message' => $userMessage];
        } catch (\Throwable $e) {
            Log::warning('AI tutor quiz failed: '.$e->getMessage());

            $this->logInteraction('tutor_quiz', $chat, AiInteractionLog::STATUS_ERROR, [
                'latency_ms'   => $this->elapsedMs($t0),
                'error'        => Str::limit($e->getMessage(), 1000),
                'request_json' => $this->redactedRequest($chat, '[quiz]', 0),
            ]);

            return ['ok' => false, 'error' => 'quiz_unavailable'];
        }
    }

    /**
     * Score a quiz attempt. Pure PHP - no AI call, and never touches any
     * v2_exam_* table (mini-quiz scores are a learning signal only).
     */
    public function submitQuizAttempt(AiTutorQuiz $quiz, Student $student, array $answers): AiTutorQuizAttempt
    {
        $questions = $quiz->questions;

        $score = $questions->filter(
            fn ($q) => isset($answers[$q->id]) && strtoupper((string) $answers[$q->id]) === strtoupper($q->correct_option)
        )->count();

        $attempt = $quiz->attempts()->create([
            'student_id'   => $student->id,
            'school_id'    => $student->school_id,
            'answers'      => $answers,
            'score'        => $score,
            'total'        => $questions->count(),
            'submitted_at' => now(),
        ]);

        $quiz->update(['status' => AiTutorQuiz::STATUS_ATTEMPTED]);

        return $attempt;
    }

    /** Validate the AI quiz payload; throws on any structural problem (belt and braces after pydantic). */
    private function validateQuiz(array $quiz): array
    {
        $questions = $quiz['questions'] ?? [];

        if (! is_array($questions) || count($questions) < 3 || count($questions) > 5) {
            throw new \RuntimeException('Quiz must contain 3-5 questions.');
        }

        foreach ($questions as $q) {
            $labels = array_map(fn ($o) => strtoupper((string) ($o['label'] ?? '')), $q['options'] ?? []);
            $ok = is_string($q['stem'] ?? null) && trim($q['stem']) !== ''
                && count($labels) >= 2 && count($labels) <= 5
                && count($labels) === count(array_unique($labels))
                && in_array(strtoupper((string) ($q['correct_option'] ?? '')), $labels, true);
            if (! $ok) {
                throw new \RuntimeException('Quiz question failed validation.');
            }
        }

        return $questions;
    }

    private function storeContextItems(AiTutorChat $chat, AiTutorMessage $message, array $manifest): void
    {
        $now = now();
        AiTutorContextItem::insert(array_map(fn ($item) => [
            'chat_id'    => $chat->id,
            'message_id' => $message->id,
            'item_type'  => $item['item_type'],
            'ref_table'  => $item['ref_table'],
            'ref_id'     => $item['ref_id'],
            'meta'       => $item['meta'] !== null ? json_encode($item['meta']) : null,
            'created_at' => $now,
        ], $manifest));
    }

    /**
     * source_context mirrors the tutor mode the student chose: question chats
     * log the question id, mistake chats log the mistake id. Authorization is
     * unaffected - it always runs through the owned, released mistake.
     */
    private function logInteraction(string $feature, AiTutorChat $chat, string $status, array $attrs): void
    {
        try {
            $this->logger->log($feature, array_merge([
                'status'              => $status,
                'source_context_type' => $chat->source_type,
                'source_context_id'   => $chat->source_type === AiTutorChat::SOURCE_QUESTION
                    ? $chat->question_id
                    : $chat->student_mistake_id,
            ], $attrs));
        } catch (\Throwable $e) {
            Log::warning('AI interaction log failed: '.$e->getMessage());
        }
    }

    /** Redacted request summary - never the full context payload (PII rule 11). */
    private function redactedRequest(AiTutorChat $chat, string $message, int $historyLen, ?array $payload = null): array
    {
        return [
            'chat_id'      => $chat->id,
            'source_type'  => $chat->source_type,
            'question_id'  => $chat->question_id,
            'message'      => Str::limit($message, 500),
            'history_len'  => $historyLen,
            'context_keys' => $payload ? array_keys($payload['grounding_assets'] ?? []) : null,
        ];
    }

    private function elapsedMs(float $t0): int
    {
        return (int) round((microtime(true) - $t0) * 1000);
    }
}
