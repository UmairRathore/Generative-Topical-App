import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { Link } from '@inertiajs/react';
import NewNoteModal from './NewNoteModal.jsx';

// ── NotesSidebar ─────────────────────────────────────────────────────────────
// Tree (notebook → section → notes) with collapsible notebooks AND sections
// (state persisted in localStorage), dividers between notebooks, and a themed
// scrollbar. Every ＋ opens the NewNoteModal (title + topic) instead of
// creating blind. Cross-note search runs on the denormalized columns.

const TYPE_CHIPS = [
    { key: 'flashcard', label: '◑ cards' },
    { key: 'widget', label: '▲ interactive' },
    { key: 'mistake', label: '✕ mistakes' },
    { key: 'callout', label: '💡 callouts' },
];

const COLLAPSE_KEY = 'nt-collapsed';

export default function NotesSidebar({ tree, urls, activeId, topics = {}, onNavigate }) {
    const [q, setQ] = useState('');
    const [blockType, setBlockType] = useState(null);
    const [results, setResults] = useState(null);
    const [busy, setBusy] = useState(false);
    const [modalCtx, setModalCtx] = useState(null); // NewNoteModal context, or null
    const timer = useRef(null);

    // Collapsed ids (notebooks + sections), persisted across visits.
    const [collapsed, setCollapsed] = useState(() => {
        try { return JSON.parse(localStorage.getItem(COLLAPSE_KEY) || '{}'); } catch { return {}; }
    });
    const toggle = (id) => setCollapsed((c) => {
        const next = { ...c, [id]: !c[id] };
        try { localStorage.setItem(COLLAPSE_KEY, JSON.stringify(next)); } catch { /* private mode */ }
        return next;
    });

    const searching = q.trim() !== '' || blockType !== null;

    useEffect(() => {
        clearTimeout(timer.current);
        if (!searching) { setResults(null); return; }
        timer.current = setTimeout(async () => {
            setBusy(true);
            try {
                const { data } = await axios.get(urls.search, {
                    params: { q: q.trim() || undefined, block_type: blockType || undefined },
                });
                setResults(data.results);
            } catch {
                setResults([]);
            } finally {
                setBusy(false);
            }
        }, 300);
        return () => clearTimeout(timer.current);
    }, [q, blockType]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <aside className="nt-sidebar">
            {/* Exit the Studio back to the main app — this zone has no left nav. */}
            <a className="nt-back-link" href={urls.dashboard}>
                <span className="nt-back-ic">←</span> Back to Dashboard
            </a>

            <div className="nt-side-head">
                <div className="nt-side-title">My Notes</div>
                <button type="button" className="nt-new-btn" onClick={() => setModalCtx({ kind: 'global' })}>
                    ＋ New note
                </button>
                <button type="button" className="nt-nav-close" onClick={onNavigate} aria-label="Close">✕</button>
            </div>

            <input
                className="nt-search"
                value={q}
                placeholder="Search notes…"
                onChange={(e) => setQ(e.target.value)}
            />
            <div className="nt-chip-row">
                {TYPE_CHIPS.map((c) => (
                    <button
                        key={c.key}
                        type="button"
                        className={`nt-chip ${blockType === c.key ? 'on' : ''}`}
                        onClick={() => setBlockType(blockType === c.key ? null : c.key)}
                    >
                        {c.label}
                    </button>
                ))}
            </div>

            {searching ? (
                <div className="nt-results">
                    {busy && results === null && <p className="nt-muted">Searching…</p>}
                    {results?.length === 0 && !busy && <p className="nt-muted">Nothing found.</p>}
                    {results?.map((r) => (
                        <Link className="nt-result" href={r.url} key={r.id} onClick={onNavigate}>
                            <b>{r.icon ? `${r.icon} ` : ''}{r.title}</b>
                            {r.snippet && <span>{r.snippet}</span>}
                            <em>{r.ago}</em>
                        </Link>
                    ))}
                </div>
            ) : (
                <nav className="nt-tree">
                    {tree.map((nb) => (
                        <div className="nt-nb" key={nb.id}>
                            <div className="nt-nb-row">
                                <button type="button" className="nt-caret" onClick={() => toggle(nb.id)}
                                    aria-label={collapsed[nb.id] ? 'Expand' : 'Collapse'}>
                                    {collapsed[nb.id] ? '▸' : '▾'}
                                </button>
                                <button type="button" className="nt-nb-title as-toggle" onClick={() => toggle(nb.id)}>
                                    {nb.emoji ? `${nb.emoji} ` : ''}{nb.title}
                                </button>
                                <button type="button" className="nt-sec-add" title={`New note in ${nb.title}`}
                                    onClick={() => setModalCtx({ kind: 'notebook', nb })}>＋</button>
                            </div>

                            {!collapsed[nb.id] && (
                                <>
                                    {nb.sections.length === 0 && (
                                        <p className="nt-muted nt-empty-sec">No notes yet — hit ＋ to start.</p>
                                    )}
                                    {nb.sections.map((s) => (
                                        <div className="nt-sec" key={s.id}>
                                            <div className="nt-sec-row">
                                                <button type="button" className="nt-caret sm" onClick={() => toggle(s.id)}
                                                    aria-label={collapsed[s.id] ? 'Expand' : 'Collapse'}>
                                                    {collapsed[s.id] ? '▸' : '▾'}
                                                </button>
                                                <button type="button" className="nt-sec-title as-toggle" onClick={() => toggle(s.id)}>
                                                    {s.title}
                                                    <span className="nt-sec-count">{s.pages.length}</span>
                                                </button>
                                                <button type="button" className="nt-sec-add" title={`New note in ${s.title}`}
                                                    onClick={() => setModalCtx({ kind: 'section', nb, section: s })}>＋</button>
                                            </div>
                                            {!collapsed[s.id] && s.pages.map((p) => (
                                                <Link
                                                    key={p.id}
                                                    href={p.url}
                                                    className={`nt-page-link ${p.id === activeId ? 'is-active' : ''}`}
                                                    onClick={onNavigate}
                                                >
                                                    <span className="nt-page-ic">{p.icon || '·'}</span>
                                                    <span className="nt-page-name">
                                                        {p.pinned ? '⌖ ' : ''}{p.favorite ? '★ ' : ''}{p.title}
                                                    </span>
                                                    <em>{p.ago}</em>
                                                </Link>
                                            ))}
                                        </div>
                                    ))}
                                </>
                            )}
                        </div>
                    ))}
                </nav>
            )}

            {modalCtx && (
                <NewNoteModal
                    ctx={modalCtx}
                    topics={topics}
                    urls={urls}
                    onClose={() => setModalCtx(null)}
                />
            )}
        </aside>
    );
}
