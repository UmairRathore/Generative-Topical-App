import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';

// ── Autosave ─────────────────────────────────────────────────────────────────
// Debounced whole-document save with optimistic concurrency. The client holds
// the content_version it loaded; the server 409s a stale save and returns its
// copy, which we hand to onStale (re-pull) instead of ever clobbering.
// axios picks up Laravel's XSRF-TOKEN cookie automatically.

const DEBOUNCE_MS = 900;

export default function useAutosave({ url, initialVersion, getPayload, onStale }) {
    const [status, setStatus] = useState('idle'); // idle | dirty | saving | saved | error
    const versionRef = useRef(initialVersion);
    const timerRef = useRef(null);
    const dirtyRef = useRef(false);
    const inflightRef = useRef(false);
    const queuedRef = useRef(false);

    const save = useCallback(async () => {
        if (inflightRef.current) { queuedRef.current = true; return; }
        inflightRef.current = true;
        dirtyRef.current = false;
        setStatus('saving');
        try {
            const { data } = await axios.put(url, {
                ...getPayload(),
                content_version: versionRef.current,
            });
            versionRef.current = data.contentVersion;
            setStatus(dirtyRef.current ? 'dirty' : 'saved');
        } catch (e) {
            if (e.response?.status === 409) {
                versionRef.current = e.response.data.contentVersion;
                onStale?.(e.response.data);
                setStatus('saved');
            } else {
                dirtyRef.current = true;
                setStatus('error');
            }
        } finally {
            inflightRef.current = false;
            if (queuedRef.current) { queuedRef.current = false; save(); }
        }
    }, [url, getPayload, onStale]);

    /** Call on every editor/title change. */
    const schedule = useCallback(() => {
        dirtyRef.current = true;
        setStatus('dirty');
        clearTimeout(timerRef.current);
        timerRef.current = setTimeout(save, DEBOUNCE_MS);
    }, [save]);

    /** Force any pending change to persist NOW (used before imports/navigation). */
    const flush = useCallback(async () => {
        clearTimeout(timerRef.current);
        if (dirtyRef.current || inflightRef.current) await save();
    }, [save]);

    /** Adopt a version the server just handed us (import / restore responses). */
    const adoptVersion = useCallback((v) => { versionRef.current = v; }, []);

    // Warn before leaving with unsaved changes; clean the timer up on unmount.
    useEffect(() => {
        const warn = (e) => { if (dirtyRef.current) { e.preventDefault(); e.returnValue = ''; } };
        window.addEventListener('beforeunload', warn);
        return () => { window.removeEventListener('beforeunload', warn); clearTimeout(timerRef.current); };
    }, []);

    return { status, schedule, flush, adoptVersion };
}
