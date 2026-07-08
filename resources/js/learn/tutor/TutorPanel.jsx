import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import MessageList from './MessageList.jsx';
import Composer from './Composer.jsx';
import Minimap from './Minimap.jsx';
import './tutor.css';

// ── AI Tutor panel ───────────────────────────────────────────────────────────
// Chat-app layout: only the thread scrolls, the composer stays pinned, and a
// fixed right-edge minimap (one marker per student prompt) jumps around long
// sessions. Quizzes live IN the thread as checkpoints: they stay in the
// history after being attempted (collapsed score bars), and while one is
// unattempted the composer locks until the student completes it.

export default function TutorPanel({ tutor }) {
    const [sourceType, setSourceType] = useState('mistake');
    const [chat, setChat] = useState(null);          // {chat, urls}
    const [messages, setMessages] = useState([]);
    const [quizzes, setQuizzes] = useState([]);
    const [opening, setOpening] = useState(true);
    const [pending, setPending] = useState(false);   // awaiting assistant reply
    const [error, setError] = useState(null);        // {text, retry?}
    const [quizBusy, setQuizBusy] = useState(false);
    const openSeq = useRef(0);
    const scrollRef = useRef(null);                  // the scrolling thread element

    const quizPending = quizzes.some((q) => q.status === 'ready');

    useEffect(() => {
        const seq = ++openSeq.current;
        setOpening(true);
        setError(null);
        setQuizzes([]);
        setChat(null);
        setMessages([]);
        axios.post(tutor.open, { source_type: sourceType })
            .then(({ data }) => {
                if (openSeq.current !== seq) return;
                setChat({ chat: data.chat, urls: data.urls });
                setMessages(data.messages || []);
                setQuizzes(data.quizzes || []);
            })
            .catch((e) => {
                if (openSeq.current !== seq) return;
                setError({
                    text: e.response?.status === 422
                        ? 'The tutor isn’t available for this question yet.'
                        : 'Couldn’t open the tutor. Please try again.',
                });
            })
            .finally(() => { if (openSeq.current === seq) setOpening(false); });
    }, [tutor.open, sourceType]);

    const send = useCallback(async (text) => {
        if (!chat || pending || quizPending) return;
        setError(null);
        setPending(true);
        setMessages((m) => [...m, {
            id: `tmp-${Date.now()}`, role: 'user', content: text,
            created_at: new Date().toISOString(),
        }]);
        try {
            const { data } = await axios.post(chat.urls.message, { message: text });
            setMessages((m) => [...m, data.message]);
        } catch (e) {
            // Server sends friendly copy for daily caps (429) and outages (502).
            const server = typeof e.response?.data?.error === 'string' ? e.response.data.error : null;
            const throttled = e.response?.status === 429;
            const dailyCap = throttled && !!server; // named limiter's friendly daily message
            setError({
                text: server || (throttled ? 'You’re going a little fast — give it a few seconds and try again.' : 'The tutor is unavailable right now.'),
                retry: dailyCap ? null : text,
            });
        } finally {
            setPending(false);
        }
    }, [chat, pending, quizPending]);

    const makeQuiz = useCallback(async () => {
        if (!chat || quizBusy || quizPending) return;
        setError(null);
        setQuizBusy(true);
        try {
            const { data } = await axios.post(chat.urls.quiz);
            setQuizzes((qs) => [...qs, data.quiz]);
        } catch (e) {
            const server = typeof e.response?.data?.error === 'string' ? e.response.data.error : null;
            const throttled = e.response?.status === 429;
            setError({ text: server || (throttled ? 'You’re going a little fast — give it a few seconds and try again.' : 'Couldn’t generate a quiz right now — please try again.') });
        } finally {
            setQuizBusy(false);
        }
    }, [chat, quizBusy, quizPending]);

    const onQuizAttempted = useCallback((quizId) => {
        setQuizzes((qs) => qs.map((q) => (q.id === quizId ? { ...q, status: 'attempted' } : q)));
    }, []);

    // Merged thread timeline: messages + quizzes in chronological order.
    const items = useMemo(() => {
        const list = [
            ...messages.map((m) => ({ kind: 'msg', at: m.created_at || '', key: 'm' + m.id, msg: m })),
            ...quizzes.map((q) => ({ kind: 'quiz', at: q.created_at || '', key: 'q' + q.id, quiz: q })),
        ];
        return list.sort((a, b) => (a.at < b.at ? -1 : a.at > b.at ? 1 : a.key < b.key ? -1 : 1));
    }, [messages, quizzes]);

    const starter = sourceType === 'mistake' ? 'Why is my answer wrong?' : 'Walk me through this question.';

    return (
        <div className="tut-panel">
            <div className="tut-bar">
                <div className="tut-modes" role="tablist" aria-label="Tutor focus">
                    <button type="button" role="tab" aria-selected={sourceType === 'mistake'}
                        className={'tut-mode' + (sourceType === 'mistake' ? ' is-active' : '')}
                        onClick={() => setSourceType('mistake')}>My mistake</button>
                    <button type="button" role="tab" aria-selected={sourceType === 'question'}
                        className={'tut-mode' + (sourceType === 'question' ? ' is-active' : '')}
                        onClick={() => setSourceType('question')}>This question</button>
                </div>
                <div className="sp" />
                <button type="button" className="tut-quizbtn" onClick={makeQuiz}
                    disabled={!chat || quizBusy || pending || quizPending}>
                    {quizBusy ? 'Building quiz…' : '✎ Quiz me'}
                </button>
            </div>

            {opening ? (
                <div className="tut-empty">Opening your tutor…</div>
            ) : (
                <>
                    <div className="tut-scrollwrap">
                        <div className="tut-scroll" ref={scrollRef}>
                            <MessageList
                                items={items}
                                pending={pending}
                                scrollRef={scrollRef}
                                onQuizAttempted={onQuizAttempted}
                            />

                            {items.length === 0 && chat && !pending && (
                                <div className="tut-hello">
                                    <p>I’m your AI tutor for this question. I’ll guide you with hints and questions — grounded in the real answer and worked solution.</p>
                                    <button type="button" className="tut-chip" onClick={() => send(starter)}>{starter}</button>
                                </div>
                            )}

                            {error && (
                                <div className="tut-error" role="alert">
                                    {error.text}
                                    {error.retry && (
                                        <button type="button" onClick={() => { const t = error.retry; setError(null); send(t); }}>
                                            Retry
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                        <Minimap scrollRef={scrollRef} count={items.length} />
                    </div>

                    <Composer
                        onSend={send}
                        disabled={!chat || pending || quizPending}
                        placeholder={quizPending ? 'Finish the quiz above to continue the chat…' : undefined}
                    />
                </>
            )}
        </div>
    );
}
