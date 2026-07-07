import React, { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import '@blocknote/mantine/style.css';
import '../notes/notes.css';
import NotesSidebar from '../notes/NotesSidebar.jsx';
import NotesEditor from '../notes/NotesEditor.jsx';
import { FigureUrlProvider } from '../notes/figureUrls.jsx';

// ── Notes · the student's block-based notebook ───────────────────────────────
// One shell for both routes: /notes (no page → empty state) and
// /notes/pages/{page} (editor). key={page.id} remounts the editor per page so
// BlockNote state never leaks between documents.

function EmptyState({ urls }) {
    return (
        <div className="nt-empty">
            <div className="nt-empty-mark">✎</div>
            <h1 className="ls-serif">Your notes live here</h1>
            <p>
                Build revision notes from text, flashcards, diagrams and callouts — or send
                worked solutions, interactive simulators and your own mistakes straight in
                from the Learning Studio. Split long notes into book-style pages with a
                page break.
            </p>
            <button type="button" className="nt-new-btn big" onClick={() => router.post(urls.createPage)}>
                ＋ Create your first note
            </button>
        </div>
    );
}

export default function Notes({ tree = [], topics = {}, page = null, urls }) {
    // Success toastr handed over from NewNoteModal via sessionStorage — it must
    // survive the Inertia redirect onto the freshly created note.
    const [toast, setToast] = useState(null);
    // Mobile: the sidebar is an off-canvas drawer, closed by default.
    const [navOpen, setNavOpen] = useState(false);

    useEffect(() => {
        try {
            const raw = sessionStorage.getItem('nt-toast');
            if (!raw) return;
            sessionStorage.removeItem('nt-toast');
            const payload = JSON.parse(raw);
            if (Date.now() - payload.t < 10000) {
                setToast(payload.m);
                const timer = setTimeout(() => setToast(null), 4500);
                return () => clearTimeout(timer);
            }
        } catch { /* ignore */ }
    }, [page?.id]);

    // Close the mobile drawer whenever the open note changes (i.e. after tapping one).
    useEffect(() => { setNavOpen(false); }, [page?.id]);

    return (
        <>
            <Head title={page?.title || 'Notes'} />
            <div className={`nt-shell ${navOpen ? 'nav-open' : ''}`}>
                <button type="button" className="nt-nav-toggle" onClick={() => setNavOpen(true)} aria-label="Open notes list">
                    ☰ <span>Notes</span>
                </button>
                <div className="nt-nav-veil" onClick={() => setNavOpen(false)} />
                <NotesSidebar tree={tree} urls={urls} activeId={page?.id} topics={topics} onNavigate={() => setNavOpen(false)} />
                <FigureUrlProvider value={page?.figureUrls}>
                    {page ? <NotesEditor key={page.id} page={page} /> : <EmptyState urls={urls} />}
                </FigureUrlProvider>
            </div>
            {toast && (
                <div className="ls-toast nt-toastr">
                    <span className="nt-toastr-ok">✓</span> {toast}
                    <button type="button" onClick={() => setToast(null)}>✕</button>
                </div>
            )}
        </>
    );
}
