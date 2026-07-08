import React, { useCallback, useEffect, useState } from 'react';

// ── Conversation minimap ─────────────────────────────────────────────────────
// ChatGPT-style: a compact stack of marker lines pinned to the chat
// container's right edge, vertically centered. One entry per STUDENT prompt
// and per quiz checkpoint, in order. Hovering the strip opens a preview panel
// listing every entry (first ~80 chars); clicking an entry or a marker jumps
// the thread there. The entry currently in view is highlighted.
// Hidden on small screens (tutor.css).

export default function Minimap({ scrollRef, count }) {
    const [marks, setMarks] = useState([]);   // [{id, kind, top, preview}]
    const [activeId, setActiveId] = useState(null);
    const [open, setOpen] = useState(false);  // hover preview panel

    const measure = useCallback(() => {
        const el = scrollRef?.current;
        if (!el) return;
        const nodes = [...el.querySelectorAll('[data-role="user"], [data-quiz]')];
        setMarks(nodes.map((n) => ({
            id: n.dataset.mid || 'quiz-' + n.dataset.quiz,
            kind: n.dataset.quiz ? 'quiz' : 'prompt',
            top: n.offsetTop,
            preview: n.dataset.quiz
                ? (n.dataset.preview || 'Mini quiz')
                : (n.textContent || '').slice(0, 80),
        })));
    }, [scrollRef]);

    const trackActive = useCallback(() => {
        const el = scrollRef?.current;
        if (!el) return;
        setMarks((current) => {
            const line = el.scrollTop + el.clientHeight * 0.35;
            let active = null;
            for (const m of current) if (m.top <= line) active = m.id;
            setActiveId(active ?? current[0]?.id ?? null);
            return current;
        });
    }, [scrollRef]);

    useEffect(() => {
        measure();
        // Late layout (markdown, quiz collapse) shifts offsets - re-measure shortly after.
        const t = setTimeout(() => { measure(); trackActive(); }, 400);
        trackActive();
        return () => clearTimeout(t);
    }, [count, measure, trackActive]);

    useEffect(() => {
        const el = scrollRef?.current;
        if (!el) return undefined;
        const onScroll = () => trackActive();
        el.addEventListener('scroll', onScroll, { passive: true });
        const ro = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(measure) : null;
        ro?.observe(el);
        return () => { el.removeEventListener('scroll', onScroll); ro?.disconnect(); };
    }, [scrollRef, trackActive, measure]);

    if (marks.length === 0) return null;

    const jump = (m) => {
        scrollRef?.current?.scrollTo({ top: Math.max(0, m.top - 40), behavior: 'smooth' });
    };

    return (
        <div
            className="tut-minimap"
            aria-label="Jump to a previous prompt or quiz"
            onMouseEnter={() => setOpen(true)}
            onMouseLeave={() => setOpen(false)}
        >
            <div className="tut-minimarks">
                {marks.map((m) => (
                    <button
                        key={m.id}
                        type="button"
                        className={'tut-minimark' + (m.id === activeId ? ' is-active' : '') + (m.kind === 'quiz' ? ' is-quiz' : '')}
                        onClick={() => jump(m)}
                        aria-label={m.preview}
                    />
                ))}
            </div>

            {open && (
                <div className="tut-minipanel" role="menu">
                    {marks.map((m) => (
                        <button
                            key={m.id}
                            type="button"
                            role="menuitem"
                            className={'tut-minirow' + (m.id === activeId ? ' is-active' : '')}
                            onClick={() => jump(m)}
                        >
                            {m.kind === 'quiz' ? '✎ ' : ''}{m.preview}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
