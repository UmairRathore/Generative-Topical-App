import React, { useEffect, useRef } from 'react';

// ── Shared bits for custom note blocks ───────────────────────────────────────
// Inputs inside a ProseMirror NodeView must stop key events from bubbling into
// the editor keymaps (else Enter/Backspace act on the document, not the field).

export function stopKeys(e) {
    e.stopPropagation();
}

/** Auto-growing textarea for block props (front/back, mermaid source…). */
export function AutoTextarea({ value, onChange, placeholder, className = '', mono = false }) {
    const ref = useRef(null);

    useEffect(() => {
        const el = ref.current;
        if (el) { el.style.height = 'auto'; el.style.height = `${el.scrollHeight}px`; }
    }, [value]);

    return (
        <textarea
            ref={ref}
            className={`nt-ta ${mono ? 'nt-ta-mono' : ''} ${className}`}
            value={value || ''}
            placeholder={placeholder}
            rows={1}
            onChange={(e) => onChange(e.target.value)}
            onKeyDown={stopKeys}
            onPaste={(e) => e.stopPropagation()}
        />
    );
}

/** Small pill label in a block's corner ("Flashcard", "Worked solution"…). */
export function BlockBadge({ icon, children }) {
    return (
        <span className="nt-badge" contentEditable={false}>
            {icon && <span className="nt-badge-ic">{icon}</span>}{children}
        </span>
    );
}
