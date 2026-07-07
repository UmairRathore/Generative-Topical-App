import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';

// ── NewNoteModal ─────────────────────────────────────────────────────────────
// Every "＋ new note" in the sidebar lands here: name the note, confirm (or
// pick) where it lives, create. ctx says where the ＋ was clicked:
//   { kind: 'global' }                          -> My Notes → General
//   { kind: 'notebook', nb }                    -> subject notebook (topic select)
//   { kind: 'section',  nb, section }           -> that exact section
// On create we stash a toast payload in sessionStorage — the Notes shell shows
// it after the Inertia redirect lands on the new note.

export default function NewNoteModal({ ctx, topics = {}, urls, onClose }) {
    const [title, setTitle] = useState('');
    const [topicId, setTopicId] = useState('');
    const [busy, setBusy] = useState(false);
    const inputRef = useRef(null);

    useEffect(() => { inputRef.current?.focus(); }, []);
    useEffect(() => {
        const esc = (e) => { if (e.key === 'Escape') onClose(); };
        window.addEventListener('keydown', esc);
        return () => window.removeEventListener('keydown', esc);
    }, [onClose]);

    const subjectTopics = ctx.kind === 'notebook' && ctx.nb.subjectId ? (topics[ctx.nb.subjectId] || []) : [];
    const topicTitle = subjectTopics.find((t) => String(t.id) === topicId)?.title;

    const destination = ctx.kind === 'section'
        ? `${ctx.nb.title} → ${ctx.section.title}`
        : ctx.kind === 'notebook'
            ? `${ctx.nb.title} → ${topicTitle || 'General'}`
            : 'My Notes → General';

    const submit = (e) => {
        e.preventDefault();
        if (busy) return;
        setBusy(true);

        const payload = { title: title.trim() || undefined };
        if (ctx.kind === 'section') {
            payload.section_id = ctx.section.id;
        } else if (ctx.kind === 'notebook' && ctx.nb.subjectId) {
            payload.subject_id = ctx.nb.subjectId;
            if (topicId) payload.topic_id = +topicId;
        }

        sessionStorage.setItem('nt-toast', JSON.stringify({
            m: `“${title.trim() || 'Untitled'}” created in ${destination}`,
            t: Date.now(),
        }));
        router.post(urls.createPage, payload, {
            onError: () => { sessionStorage.removeItem('nt-toast'); setBusy(false); },
        });
    };

    // Portal to <body>: the sidebar's own stacking/filter context must never
    // position this — a modal belongs to the viewport.
    return createPortal(
        <>
            <div className="nt-drawer-veil" onClick={busy ? undefined : onClose} />
            <form className="nt-modal" onSubmit={submit}>
                <div className="nt-drawer-head">
                    <b>New note</b>
                    <div className="sp" />
                    <button type="button" className="nt-icon-btn" onClick={onClose} disabled={busy}>✕</button>
                </div>

                <label className="nt-modal-lbl" htmlFor="nt-new-title">Title</label>
                <input
                    id="nt-new-title"
                    ref={inputRef}
                    className="nt-search"
                    value={title}
                    maxLength={200}
                    placeholder="e.g. Refraction — key ideas"
                    onChange={(e) => setTitle(e.target.value)}
                />

                {subjectTopics.length > 0 && (
                    <>
                        <label className="nt-modal-lbl" htmlFor="nt-new-topic">Topic</label>
                        <select
                            id="nt-new-topic"
                            className="nt-search nt-modal-select"
                            value={topicId}
                            onChange={(e) => setTopicId(e.target.value)}
                        >
                            <option value="">General — no topic yet</option>
                            {subjectTopics.map((t) => (
                                <option key={t.id} value={t.id}>{t.title}</option>
                            ))}
                        </select>
                    </>
                )}

                <p className="nt-modal-dest">Will be created in <b>{destination}</b></p>

                <div className="nt-modal-actions">
                    <button type="button" className="nt-mini-btn" onClick={onClose} disabled={busy}>Cancel</button>
                    <button type="submit" className="nt-new-btn" disabled={busy}>
                        {busy ? 'Creating…' : 'Create note'}
                    </button>
                </div>
            </form>
        </>,
        document.body,
    );
}
