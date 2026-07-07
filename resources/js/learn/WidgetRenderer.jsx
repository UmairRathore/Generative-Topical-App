import React, { Suspense, useMemo } from 'react';
import { registry, hasWidget } from '../widgets/registry.js';

// ── WidgetRenderer ───────────────────────────────────────────────────────────
// Renders a widget by { type, config } inside an Inertia page, reusing the exact
// same registry + component files as the island runtime. Lazy-loaded/code-split.
// Pass onAddToNote when notes is wired; omit it and the widget stays notes-ready
// but shows no save affordance (notes is deferred).
const _cache = {};

export default function WidgetRenderer({ type, config, onAddToNote }) {
    const ok = type && hasWidget(type);
    const Comp = useMemo(
        () => (ok ? (_cache[type] || (_cache[type] = React.lazy(registry[type]))) : null),
        [type, ok],
    );
    if (!Comp) return null;
    return (
        <div className="cw-root">
            <Suspense fallback={<div className="cw-loading">Loading interactive…</div>}>
                <Comp config={config || {}} onAddToNote={onAddToNote} />
            </Suspense>
        </div>
    );
}
