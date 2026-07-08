import React, { useState } from 'react';
import axios from 'axios';

// ── Mini quiz ────────────────────────────────────────────────────────────────
// Temporary tutor practice — scored into v2_ai_tutor_quiz_attempts, never into
// official exam results. A quiz is a checkpoint that lives in the thread:
// unattempted, it can't be dismissed (the panel locks the composer); once
// attempted it stays in the history as a collapsed, expandable score bar.
// Attempted quizzes restored from the server arrive with correct_option/
// explanation on each question plus the saved result/answers.

export default function QuizCard({ quiz, onAttempted }) {
    const restored = quiz.status === 'attempted' && quiz.result;
    const [answers, setAnswers] = useState(restored ? (quiz.result.answers || {}) : {});
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const [serverResults, setServerResults] = useState(null); // fresh submit results
    const [result, setResult] = useState(restored ? { score: quiz.result.score, total: quiz.result.total } : null);
    const [collapsed, setCollapsed] = useState(!!restored);

    const allAnswered = quiz.questions.every((q) => answers[q.id]);

    const submit = async () => {
        if (!allAnswered || busy) return;
        setBusy(true);
        setError(null);
        try {
            const { data } = await axios.post(quiz.attemptUrl, { answers });
            setServerResults(data.results || []);
            setResult({ score: data.score, total: data.total });
            setCollapsed(true); // fold to the score bar; expandable for review
            onAttempted?.(quiz.id, data);
        } catch (e) {
            if (e.response?.status === 409) {
                setError('This quiz was already attempted — generate a new one.');
                onAttempted?.(quiz.id, null); // don't hold the chat hostage on a stale quiz
            } else {
                setError('Couldn’t submit — please try again.');
            }
        } finally {
            setBusy(false);
        }
    };

    // Verdict per question: fresh submits use the server's results; restored
    // quizzes derive it from the shipped correct_option + saved answers.
    const verdictFor = (q) => {
        const fromServer = serverResults?.find((r) => r.id === q.id);
        if (fromServer) return fromServer;
        if (result && q.correct_option) {
            const your = String(answers[q.id] ?? '').toUpperCase();
            return {
                correct: your === String(q.correct_option).toUpperCase(),
                correct_option: q.correct_option,
                explanation: q.explanation,
            };
        }
        return null;
    };

    return (
        <div
            className={'tut-quiz' + (collapsed ? ' is-collapsed' : '')}
            data-quiz={quiz.id}
            data-preview={quiz.title || 'Mini quiz'}
        >
            <div
                className="tut-quiz-head"
                role={result ? 'button' : undefined}
                tabIndex={result ? 0 : undefined}
                onClick={() => result && setCollapsed((c) => !c)}
                onKeyDown={(e) => result && (e.key === 'Enter' || e.key === ' ') && setCollapsed((c) => !c)}
            >
                <b>{quiz.title || 'Quick check'}</b>
                {result
                    ? (
                        <span className={'tut-score' + (result.score === result.total ? ' is-perfect' : '')}>
                            {result.score}/{result.total}
                        </span>
                    )
                    : <span className="tut-quiz-gate">finish to continue</span>}
                <div className="sp" />
                {result && (
                    <span className="tut-quiz-fold" aria-label={collapsed ? 'Expand quiz' : 'Collapse quiz'}>
                        {collapsed ? '▸ review' : '▾ collapse'}
                    </span>
                )}
            </div>

            {!collapsed && (
                <>
                    {quiz.questions.map((q, i) => {
                        const verdict = verdictFor(q);
                        return (
                            <div key={q.id} className="tut-quiz-q">
                                <div className="tut-quiz-stem"><span>{i + 1}.</span> {q.stem}</div>
                                <div className="tut-quiz-opts">
                                    {q.options.map((o) => {
                                        const picked = String(answers[q.id] ?? '') === o.label;
                                        let cls = 'tut-quiz-opt' + (picked ? ' is-picked' : '');
                                        if (verdict) {
                                            if (o.label === verdict.correct_option) cls += ' is-correct';
                                            else if (picked && !verdict.correct) cls += ' is-wrong';
                                        }
                                        return (
                                            <label key={o.label} className={cls}>
                                                <input
                                                    type="radio"
                                                    name={`quiz-${quiz.id}-${q.id}`}
                                                    value={o.label}
                                                    checked={picked}
                                                    disabled={!!result}
                                                    onChange={() => setAnswers((a) => ({ ...a, [q.id]: o.label }))}
                                                />
                                                <span className="tut-quiz-key">{o.label}</span>
                                                <span>{o.text}</span>
                                            </label>
                                        );
                                    })}
                                </div>
                                {verdict && (
                                    <div className={'tut-quiz-why' + (verdict.correct ? ' is-ok' : '')}>
                                        {verdict.correct ? '✓ Correct.' : `✗ Correct answer: ${verdict.correct_option}.`}
                                        {verdict.explanation ? ` ${verdict.explanation}` : ''}
                                    </div>
                                )}
                            </div>
                        );
                    })}

                    {error && <div className="tut-error" role="alert">{error}</div>}

                    {!result && (
                        <button type="button" className="tut-quiz-submit" onClick={submit} disabled={!allAnswered || busy}>
                            {busy ? 'Checking…' : 'Check my answers'}
                        </button>
                    )}
                </>
            )}
        </div>
    );
}
