import React, { useEffect, useState } from 'react';
import axios from 'axios';
import './notes.css';

// ── AddToNotePicker ──────────────────────────────────────────────────────────
// The host-app side of the notes contract. Given a pending import payload
// ({source: 'asset'|'mistake'|'widget_state', …}), lets the student pick a
// target page (or create one) and POSTs the import anchored to the mistake.
// onDone receives {pageTitle, pageUrl} for the "Added →" confirmation.

export default function AddToNotePicker({ notes, payload, suggestedTitle, onClose, onDone }) {
    const [tree, setTree] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    // A note with page breaks gets a second step: pick WHICH page (section)
    // the import lands in. { note, sections } while choosing; null otherwise.
    const [choice, setChoice] = useState(null);

    useEffect(() => {
        let alive = true;
        axios.get(notes.tree)
            .then((r) => { if (alive) setTree(r.data.tree); })
            .catch(() => { if (alive) setError('Couldn’t load your notebooks.'); });
        return () => { alive = false; };
    }, [notes.tree]);

    const doImport = async (importUrl, noteTitle, beforeBlockId = null) => {
        setBusy(true);
        setError(null);
        try {
            const { data } = await axios.post(importUrl, {
                ...payload,
                mistake_id: notes.mistakeId,
                before_block_id: beforeBlockId || undefined,
            });
            onDone({ pageTitle: noteTitle, pageUrl: data.pageUrl });
        } catch (e) {
            setError(e.response?.status === 404
                ? 'That content isn’t available to import.'
                : 'Import failed — please try again.');
            setBusy(false);
        }
    };

    /** Click a note: no page breaks -> straight in at the end; else choose the section. */
    const pickNote = async (note) => {
        setBusy(true);
        setError(null);
        try {
            const { data } = await axios.get(note.outlineUrl);
            if ((data.sections?.length ?? 1) <= 1) {
                await doImport(note.importUrl, note.title);
            } else {
                setChoice({ note, sections: data.sections });
                setBusy(false);
            }
        } catch {
            await doImport(note.importUrl, note.title); // outline unavailable -> append
        }
    };

    /** Create a note then import into it. target: {section_id} OR {subject_id, topic_id} (provisions the home). */
    const createAndImport = async (target = {}) => {
        setBusy(true);
        setError(null);
        try {
            const { data: page } = await axios.post(
                notes.createPage,
                { ...target, title: suggestedTitle || undefined },
                { headers: { Accept: 'application/json' } },
            );
            await doImport(page.importUrl, page.title);
        } catch {
            setError('Couldn’t create the note — please try again.');
            setBusy(false);
        }
    };

    // ── Suggested home: the notebook/section matching the mistake's curriculum ──
    const suggest = notes.suggest?.subjectId ? notes.suggest : null;
    const sugNotebook = suggest ? tree?.find((nb) => nb.subjectId === suggest.subjectId) : null;
    const sugSection = suggest?.topicId
        ? sugNotebook?.sections.find((s) => s.topicId === suggest.topicId)
        : null;

    return (
        <>
            <div className="nt-drawer-veil" onClick={busy ? undefined : onClose} />
            <div className="nt-picker">
                <div className="nt-drawer-head">
                    <b>Add to notes</b>
                    <div className="sp" />
                    <button type="button" className="nt-icon-btn" onClick={onClose} disabled={busy}>✕</button>
                </div>

                {error && <p className="nt-picker-err">{error}</p>}
                {tree === null && !error && <p className="nt-muted">Loading your notebooks…</p>}

                {!choice && suggest && tree && (
                    <div className="nt-suggest">
                        <div className="nt-suggest-head">
                            <span className="nt-badge">★ Suggested</span>
                            <span className="nt-suggest-path">
                                {suggest.subjectName}{suggest.topicName ? <> → {suggest.topicName}</> : null}
                            </span>
                        </div>
                        {sugSection?.pages.map((p) => (
                            <button
                                key={p.id}
                                type="button"
                                className="nt-page-link as-btn"
                                disabled={busy}
                                onClick={() => pickNote(p)}
                            >
                                <span className="nt-page-ic">{p.icon || '·'}</span>
                                <span className="nt-page-name">{p.title}</span>
                                <em>{p.ago}</em>
                            </button>
                        ))}
                        <button
                            type="button"
                            className="nt-suggest-new"
                            disabled={busy}
                            onClick={() => createAndImport({ subject_id: suggest.subjectId, topic_id: suggest.topicId || undefined })}
                        >
                            ＋ New note in {suggest.topicName || suggest.subjectName}
                            {!sugSection && <span className="nt-suggest-hint">
                                {sugNotebook ? ' · creates the section' : ' · creates the notebook'}
                            </span>}
                        </button>
                    </div>
                )}

                {choice ? (
                    <div className="nt-picker-sections">
                        <button type="button" className="nt-mini-btn" disabled={busy} onClick={() => setChoice(null)}>
                            ← All notes
                        </button>
                        <p className="nt-muted" style={{ margin: '10px 0 6px' }}>
                            Where in <b>{choice.note.title}</b>?
                        </p>
                        {choice.sections.map((s, i) => (
                            <button
                                key={i}
                                type="button"
                                className="nt-page-link as-btn"
                                disabled={busy}
                                onClick={() => doImport(choice.note.importUrl, choice.note.title, s.insertBeforeId)}
                            >
                                <span className="nt-page-ic">⤓</span>
                                <span className="nt-page-name">{s.label}</span>
                                <em>{i === choice.sections.length - 1 ? 'end of note' : 'end of this page'}</em>
                            </button>
                        ))}
                    </div>
                ) : tree?.map((nb) => (
                    <div className="nt-nb" key={nb.id}>
                        <div className="nt-nb-title">{nb.emoji ? `${nb.emoji} ` : ''}{nb.title}</div>
                        {nb.sections.map((s) => (
                            <div className="nt-sec" key={s.id}>
                                <div className="nt-sec-row">
                                    <span className="nt-sec-title">{s.title}</span>
                                    <button
                                        type="button"
                                        className="nt-sec-add"
                                        title="Add to a new note here"
                                        disabled={busy}
                                        onClick={() => createAndImport({ section_id: s.id })}
                                    >
                                        ＋ new note
                                    </button>
                                </div>
                                {s.pages.map((p) => (
                                    <button
                                        key={p.id}
                                        type="button"
                                        className="nt-page-link as-btn"
                                        disabled={busy}
                                        onClick={() => pickNote(p)}
                                    >
                                        <span className="nt-page-ic">{p.icon || '·'}</span>
                                        <span className="nt-page-name">{p.title}</span>
                                        <em>{p.ago}</em>
                                    </button>
                                ))}
                            </div>
                        ))}
                    </div>
                ))}
            </div>
        </>
    );
}
