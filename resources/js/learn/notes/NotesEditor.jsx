import React, { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import { filterSuggestionItems } from '@blocknote/core';
import { useCreateBlockNote, getDefaultReactSlashMenuItems, SuggestionMenuController } from '@blocknote/react';
import { BlockNoteView } from '@blocknote/mantine';
import { schema, studySlashItems } from './schema.jsx';
import useAutosave from './useAutosave.js';
import VersionDrawer from './VersionDrawer.jsx';

// ── NotesEditor ──────────────────────────────────────────────────────────────
// One open page: title, meta actions (pin/favorite/history/delete), status
// pill, and the BlockNote document. Whole-document autosave; restore and
// stale-409 both re-pull by replacing the editor's blocks.

const STATUS_LABEL = {
    idle:   'Saved',
    dirty:  'Unsaved…',
    saving: 'Saving…',
    saved:  'Saved',
    error:  'Couldn’t save — retrying on next edit',
};

export default function NotesEditor({ page }) {
    const [title, setTitle] = useState(page.title);
    const [pinned, setPinned] = useState(page.pinned);
    const [favorite, setFavorite] = useState(page.favorite);
    const [history, setHistory] = useState(false);
    const titleRef = useRef(page.title);
    const titleEl = useRef(null);

    // The title is a textarea so a long name wraps to multiple lines instead of
    // clipping; keep its height matched to the content.
    const growTitle = useCallback(() => {
        const el = titleEl.current;
        if (el) { el.style.height = 'auto'; el.style.height = `${el.scrollHeight}px`; }
    }, []);
    useEffect(growTitle, [title, growTitle]);

    const editor = useCreateBlockNote({
        schema,
        initialContent: page.document?.length ? page.document : undefined,
        // Enables the image block's Upload tab + paste/drop of images. Files go
        // to the private notes-images store; the returned URL is owner-only.
        uploadFile: async (file) => {
            const form = new FormData();
            form.append('file', file);
            const { data } = await axios.post(page.urls.upload, form);
            return data.url;
        },
    });

    const replaceDocument = useCallback((doc, newTitle) => {
        editor.replaceBlocks(editor.document, doc?.length ? doc : [{ type: 'paragraph' }]);
        if (typeof newTitle === 'string') { setTitle(newTitle); titleRef.current = newTitle; }
    }, [editor]);

    const { status, schedule, flush, adoptVersion } = useAutosave({
        url: page.urls.update,
        initialVersion: page.contentVersion,
        getPayload: () => ({ title: titleRef.current, document: editor.document }),
        onStale: (server) => replaceDocument(server.document, server.title),
    });

    const onTitle = (e) => {
        setTitle(e.target.value);
        titleRef.current = e.target.value;
        schedule();
    };

    const toggleMeta = async (key, value, set) => {
        set(value);
        try { await axios.patch(page.urls.meta, { [key]: value }); }
        catch { set(!value); }
    };

    const destroy = () => {
        if (confirm('Delete this note? Its version history goes with it.')) {
            router.delete(page.urls.destroy);
        }
    };

    return (
        <div className="nt-editor-pane">
            <div className="nt-page-bar">
                <span className={`nt-status s-${status}`}>{STATUS_LABEL[status]}</span>
                <div className="sp" />
                <button type="button" className={`nt-icon-btn ${pinned ? 'on' : ''}`} title="Pin"
                    onClick={() => toggleMeta('pinned', !pinned, setPinned)}>⌖</button>
                <button type="button" className={`nt-icon-btn ${favorite ? 'on' : ''}`} title="Favorite"
                    onClick={() => toggleMeta('favorite', !favorite, setFavorite)}>★</button>
                <button type="button" className="nt-icon-btn" title="Version history"
                    onClick={() => setHistory(true)}>↺</button>
                <button type="button" className="nt-icon-btn danger" title="Delete note" onClick={destroy}>✕</button>
            </div>

            <textarea
                ref={titleEl}
                className="nt-title"
                value={title}
                placeholder="Untitled"
                maxLength={200}
                rows={1}
                spellCheck={false}
                onChange={onTitle}
                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); e.target.blur(); } }}
            />

            <div className="nt-editor">
                <BlockNoteView editor={editor} theme="dark" slashMenu={false} onChange={schedule}>
                    <SuggestionMenuController
                        triggerCharacter="/"
                        getItems={async (query) => filterSuggestionItems(
                            [...getDefaultReactSlashMenuItems(editor), ...studySlashItems(editor)],
                            query,
                        )}
                    />
                </BlockNoteView>
            </div>

            {history && (
                <VersionDrawer
                    url={page.urls.versions}
                    onClose={() => setHistory(false)}
                    onRestore={async (data) => {
                        await flush();
                        replaceDocument(data.document, data.title);
                        adoptVersion(data.contentVersion);
                        setHistory(false);
                    }}
                />
            )}
        </div>
    );
}
