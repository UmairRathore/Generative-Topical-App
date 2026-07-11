import React, { useEffect, useRef } from 'react';
import Markdown from '../lib/markdown.jsx';
import TypingIndicator from './TypingIndicator.jsx';
import QuizCard from './QuizCard.jsx';

/**
 * The merged thread: chat turns and quiz checkpoints in one chronological
 * stream. Quizzes stay in the history after being attempted.
 */
export default function MessageList({ items, pending, scrollRef, onQuizAttempted, canSaveNotes, onSaveAnswer }) {
    const endRef = useRef(null);

    // Keep the newest turn in view - scrolls the thread container only.
    useEffect(() => {
        const el = scrollRef?.current;
        if (el) el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
        else endRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, [items.length, pending, scrollRef]);

    if (!items.length && !pending) return null;

    return (
        <div className="tut-thread" aria-live="polite">
            {items.map((item) => (
                item.kind === 'msg' ? (
                    <div key={item.key} data-mid={item.msg.id} data-role={item.msg.role} className={'tut-msg is-' + item.msg.role}>
                        {item.msg.role === 'assistant'
                            ? (
                                <div className="tut-msg-body">
                                    <Markdown text={item.msg.content} />
                                    {canSaveNotes && onSaveAnswer && typeof item.msg.id === 'number' && (
                                        <div className="tut-savenote-row">
                                            <button type="button" className="tut-savenote" onClick={() => onSaveAnswer(item.msg.id)}>
                                                ＋ Save to notes
                                            </button>
                                            {item.msg.saved?.count > 0
                                                ? (
                                                    <span
                                                        className="tut-savedtag"
                                                        title={(item.msg.saved.pages || []).map((p) => p.title).filter(Boolean).join(', ') || undefined}
                                                    >
                                                        ✓ Saved {item.msg.saved.count}×
                                                    </span>
                                                )
                                                : <span className="tut-savedtag is-none">Not previously saved</span>}
                                        </div>
                                    )}
                                </div>
                            )
                            : <div className="tut-msg-body">{item.msg.content}</div>}
                    </div>
                ) : (
                    <QuizCard key={item.key} quiz={item.quiz} onAttempted={onQuizAttempted} />
                )
            ))}
            {pending && <TypingIndicator />}
            <div ref={endRef} />
        </div>
    );
}
