// ── Tutor action intent (UX fast-path) ───────────────────────────────────────
// Pure, deterministic, conservative classifier for the tutor composer. Returns
// 'quiz' for explicit "make me a quiz" commands, else 'chat'.
//
// This is ONLY a fast-path so the composer can route to the quiz flow (and show
// "Building quiz…") instantly. The BACKEND is the source of truth - it re-runs
// the equivalent detector (App\Services\AI\TutorActionDetector) on /messages, so
// anything this misses is still classified correctly server-side. Keep the two
// in sync. NOT an LLM classifier and must not become one.
//
// Triggers 'quiz':  quiz me · test me on this · generate a quiz ·
//                   create 5 practice questions · give me 10 MCQs ·
//                   start a quiz on this topic
// Stays  'chat':    explain this quiz · why did the quiz mark me wrong ·
//                   review my quiz · what should I study before the quiz ·
//                   make the previous quiz easier · what is a quiz

// Discussion ABOUT a quiz/test — only disqualifies when a literal quiz/test
// token is present, so "ask me some questions about this" is unaffected.
const DISCUSSION = /\b(explain|explains|why|what\s+is|what's|whats|confus\w*|about|from|regarding|understand|help\s+me\s+with|question\s+\d|review|study|before|previous|earlier|easier|harder|last)\b/;
const QUIZ_WORD = /\b(quiz|quizzes|test)\b/;

// A request verb tightly followed (articles / counts / small filler only)
// by a quiz noun. The tight coupling keeps "make the previous quiz easier" out.
const GENERATE = new RegExp(
    '\\b(?:' +
    'quiz\\s+me|test\\s+me' +
    '|(?:generate|create|make|set|start|build|prepare|write|design|give\\s+me|ask\\s+me|get\\s+me|send\\s+me|give|ask)' +
    '(?:\\s+(?:a|an|the|some|a\\s+few|another|couple(?:\\s+of)?|\\d+|me|us|new|quick|short|small|mini|practice|more))*' +
    '\\s+(?:quiz(?:zes)?|test|mcqs?|multiple[-\\s]?choice(?:\\s+questions?)?|practice\\s+questions?|questions)' +
    ')\\b',
);

/**
 * @param {string} message
 * @returns {'quiz'|'chat'}
 */
export function detectTutorAction(message) {
    const text = String(message || '').toLowerCase().trim();
    if (!text) return 'chat';

    // A question/discussion that mentions a quiz is chat, not a command.
    if (DISCUSSION.test(text) && QUIZ_WORD.test(text)) return 'chat';

    return GENERATE.test(text) ? 'quiz' : 'chat';
}

export default detectTutorAction;
