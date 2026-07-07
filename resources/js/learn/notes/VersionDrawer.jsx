import React, { useEffect, useState } from 'react';
import axios from 'axios';

// ── VersionDrawer ────────────────────────────────────────────────────────────
// Right-hand drawer listing the page's snapshots (newest first). Restore asks,
// then POSTs; the server snapshots the current state first so it's reversible.

export default function VersionDrawer({ url, onClose, onRestore }) {
    const [versions, setVersions] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(false);

    useEffect(() => {
        let alive = true;
        axios.get(url)
            .then((r) => { if (alive) setVersions(r.data.versions); })
            .catch(() => { if (alive) setError(true); });
        return () => { alive = false; };
    }, [url]);

    const restore = async (v) => {
        if (busy || !confirm(`Restore “${v.title}” from ${v.ago}? The current version is snapshotted first.`)) return;
        setBusy(true);
        try {
            const { data } = await axios.post(v.restoreUrl);
            onRestore(data);
        } catch {
            setError(true);
            setBusy(false);
        }
    };

    return (
        <>
            <div className="nt-drawer-veil" onClick={onClose} />
            <aside className="nt-drawer">
                <div className="nt-drawer-head">
                    <b>Version history</b>
                    <span className="nt-muted">last {versions?.length ?? '…'} snapshots</span>
                    <div className="sp" />
                    <button type="button" className="nt-icon-btn" onClick={onClose}>✕</button>
                </div>

                {error && <p className="nt-muted">Couldn’t load history — close and try again.</p>}
                {!error && versions === null && <p className="nt-muted">Loading…</p>}
                {versions?.length === 0 && <p className="nt-muted">No snapshots yet — they’re taken automatically as you edit.</p>}

                <div className="nt-drawer-list">
                    {versions?.map((v) => (
                        <div className="nt-version" key={v.id}>
                            <div className="nt-version-info">
                                <b>{v.title}</b>
                                <span className="nt-muted">{v.ago} · {v.blocks} block{v.blocks === 1 ? '' : 's'}</span>
                            </div>
                            <button type="button" className="nt-mini-btn" disabled={busy} onClick={() => restore(v)}>
                                Restore
                            </button>
                        </div>
                    ))}
                </div>
            </aside>
        </>
    );
}
