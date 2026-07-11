<?php

namespace App\Services\AI;

/**
 * Server-side tutor intent classifier - the SOURCE OF TRUTH for whether a
 * typed composer message is an explicit "make me a quiz" command or ordinary
 * chat. Mirrors resources/js/learn/tutor/detectTutorAction.js (which is only a
 * UX fast-path); the backend must reach the same verdict even when the client
 * detector is bypassed or the endpoint is POSTed directly.
 *
 * Deliberately pure + deterministic - NOT an LLM. It is conservative: anything
 * that reads like a discussion ABOUT a quiz, or that doesn't clearly pair a
 * request verb with a quiz noun, stays chat. False negatives (miss a quiz
 * request) are cheap - the student can press "Quiz me"; false positives (hijack
 * a normal question into a quiz) are jarring, so we avoid them.
 *
 *   quiz_request: "quiz me" · "test me on this" · "generate a quiz" ·
 *                 "create 5 practice questions" · "give me 10 MCQs" ·
 *                 "start a quiz on this topic"
 *   chat:         "explain this quiz" · "why did the quiz mark me wrong?" ·
 *                 "review my quiz" · "what should I study before the quiz?" ·
 *                 "make the previous quiz easier" · "what is a quiz?"
 */
class TutorActionDetector
{
    public const ACTION_CHAT = 'chat';
    public const ACTION_QUIZ = 'quiz_request';

    /**
     * Words that mark a message as talking ABOUT a quiz rather than asking for
     * one. Only disqualifies when the literal token "quiz"/"test" is also
     * present (so "ask me some questions about this" is unaffected).
     */
    private const DISCUSSION = '/\b(?:explain|explains|why|what\s+is|what\'?s|whats|confus\w*|about|from|regarding|understand|help\s+me\s+with|question\s+\d|review|study|before|previous|earlier|easier|harder|last)\b/';

    /** The literal quiz/test token the discussion guard keys on. */
    private const QUIZ_WORD = '/\b(?:quiz|quizzes|test)\b/';

    /**
     * A request verb tightly followed (only articles / counts / small filler
     * between) by a quiz noun. The tight coupling is what keeps "make the
     * previous quiz easier" out: "the previous" is not filler, so the verb
     * never reaches the noun.
     */
    private const GENERATE =
        '/\b(?:'
        .'quiz\s+me|test\s+me'
        .'|(?:generate|create|make|set|start|build|prepare|write|design|give\s+me|ask\s+me|get\s+me|send\s+me|give|ask)'
        .'(?:\s+(?:a|an|the|some|a\s+few|another|couple(?:\s+of)?|\d+|me|us|new|quick|short|small|mini|practice|more))*'
        .'\s+(?:quiz(?:zes)?|test|mcqs?|multiple[-\s]?choice(?:\s+questions?)?|practice\s+questions?|questions)'
        .')\b/';

    public function detect(?string $message): string
    {
        $text = strtolower(trim((string) $message));

        if ($text === '') {
            return self::ACTION_CHAT;
        }

        // A question/discussion that mentions a quiz is chat, not a command.
        if (preg_match(self::DISCUSSION, $text) && preg_match(self::QUIZ_WORD, $text)) {
            return self::ACTION_CHAT;
        }

        return preg_match(self::GENERATE, $text) ? self::ACTION_QUIZ : self::ACTION_CHAT;
    }

    /** Convenience predicate. */
    public function isQuizRequest(?string $message): bool
    {
        return $this->detect($message) === self::ACTION_QUIZ;
    }
}
