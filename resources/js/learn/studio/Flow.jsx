import React, { useEffect, useRef, useState } from 'react';

// ── Flow (mermaid) ───────────────────────────────────────────────────────────
// Renders a mermaid source string (the `mermaid` asset) to an inline SVG.
// mermaid is heavy, so it's dynamically imported → its own lazy chunk, loaded
// only when the Flow tab is first opened. Falls back to the source on error.

export default function Flow({ source }) {
    const ref = useRef(null);
    const [err, setErr] = useState(false);

    useEffect(() => {
        if (!source) return;
        let alive = true;
        (async () => {
            try {
                const mermaid = (await import('mermaid')).default;
                mermaid.initialize({
                    startOnLoad: false,
                    theme: 'dark',
                    securityLevel: 'loose',           // source is trusted, approved asset content
                    themeVariables: { fontFamily: 'Inter, system-ui, sans-serif', primaryColor: '#0B1E15', lineColor: '#34D399' },
                });
                const id = 'mmd-' + Math.random().toString(36).slice(2);
                const { svg } = await mermaid.render(id, source);
                if (alive && ref.current) ref.current.innerHTML = svg;
            } catch (e) {
                console.error('[flow] mermaid render failed', e);
                if (alive) setErr(true);
            }
        })();
        return () => { alive = false; };
    }, [source]);

    if (err) return <pre className="ls-code">{source}</pre>;
    return <div className="ls-flow" ref={ref} />;
}
