import React, { useEffect, useRef, useState } from 'react';

// ── Composer ─────────────────────────────────────────────────────────────────
// ChatGPT-style pill. Compact: one row, text + send circle. Grown (2+ lines):
// the pill becomes a column - the scrollable text region sits ABOVE the send
// button, so the scrollbar never runs behind the icon. Caps: ~12 lines on
// desktop, ~4 on mobile, then internal scroll. Mobile only: ⤢ expands to a
// full-panel compose view. Enter sends, Shift+Enter makes a new line.

const isMobile = () => window.matchMedia('(max-width: 920px)').matches;
const growCap = () => (isMobile() ? 112 : 285); // ≈ 4 lines mobile, ≈ 12 lines desktop

function SendIcon() {
    return (
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor"
            strokeWidth="3.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M12 19V5" />
            <path d="M5 12l7-7 7 7" />
        </svg>
    );
}

export default function Composer({ onSend, disabled, placeholder }) {
    const [text, setText] = useState('');
    const [grown, setGrown] = useState(false);
    const [expanded, setExpanded] = useState(false);
    const taRef = useRef(null);
    const expandedRef = useRef(false);

    const autosize = (el) => {
        if (!el || expandedRef.current) return;
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, growCap()) + 'px';
        // Grow-only latch: the row→column flip changes the textarea's width,
        // so deriving "grown" from the measured height in BOTH directions
        // oscillates forever on wrap-boundary text (infinite render loop).
        // It only un-grows when the draft is cleared or sent.
        if (el.value === '') setGrown(false);
        else if (el.scrollHeight > 40 || el.value.includes('\n')) setGrown(true);
    };

    // Keep the ref in sync and re-fit the inline height after collapsing -
    // the draft must come back at its full (capped) height, not one line.
    useEffect(() => {
        expandedRef.current = expanded;
        if (!expanded) autosize(taRef.current);
    }, [expanded]);

    // The row↔column flip changes the textarea's width (and therefore how the
    // text wraps) - re-measure AFTER the flip so the field fits every line
    // exactly, with no clipped text and no dead space beneath.
    useEffect(() => {
        if (!expandedRef.current) autosize(taRef.current);
    }, [grown]);

    const reset = () => {
        setText('');
        setGrown(false);
        setExpanded(false);
        expandedRef.current = false;
        if (taRef.current) taRef.current.style.height = 'auto';
    };

    const submit = () => {
        const t = text.trim();
        if (!t || disabled) return;
        reset();
        onSend(t);
    };

    const showExpand = isMobile() && (expanded || grown);

    return (
        <div className={'tut-composer' + (expanded ? ' is-expanded' : '')}>
            <div
                className={'tut-input' + (grown || expanded ? ' is-grown' : '')}
                onClick={(e) => { if (e.target.tagName !== 'BUTTON' && !e.target.closest('button')) taRef.current?.focus(); }}
            >
                <textarea
                    ref={taRef}
                    value={text}
                    maxLength={2000}
                    rows={1}
                    placeholder={placeholder || 'Ask the tutor…'}
                    disabled={disabled}
                    onChange={(e) => { setText(e.target.value); autosize(e.target); }}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            submit();
                        }
                        if (e.key === 'Escape' && expanded) setExpanded(false);
                    }}
                />
                {showExpand && (
                    <button
                        type="button"
                        className="tut-expand"
                        onClick={() => setExpanded((x) => !x)}
                        aria-label={expanded ? 'Collapse input' : 'Expand input'}
                        title={expanded ? 'Collapse' : 'Expand'}
                    >
                        {expanded ? '⤡' : '⤢'}
                    </button>
                )}
                <button type="button" className="tut-send" onClick={submit} disabled={disabled || !text.trim()} aria-label="Send">
                    <SendIcon />
                </button>
            </div>
        </div>
    );
}
