import React, { useState } from 'react';

// ── Shared design-system primitives for widgets ──────────────────────────────
// Kept intentionally small; grow as the library grows. Everything is styled by
// the scoped `.cw-*` classes in widgets.css so a widget looks right anywhere
// (a showcase page, a note, an AI-tutor message).

export function Stat({ label, value, unit, sub, tone = 'acc' }) {
    return (
        <div className="cw-stat">
            <div className="cw-stat-lab">{label}</div>
            <div className={`cw-stat-num cw-${tone}`}>
                {value}
                {unit ? <small>{unit}</small> : null}
            </div>
            {sub ? <div className="cw-stat-sub">{sub}</div> : null}
        </div>
    );
}

export function Slider({ label, value, min, max, step = 1, onChange, format, tone }) {
    const pct = ((value - min) / (max - min)) * 100;
    return (
        <div className="cw-slider">
            <label>
                <span>{label}</span>
                <b style={tone ? { color: tone } : undefined}>{format ? format(value) : value}</b>
            </label>
            <input
                type="range"
                min={min}
                max={max}
                step={step}
                value={value}
                onChange={(e) => onChange(+e.target.value)}
                style={{ '--fill': pct + '%', '--c': tone || 'var(--cw-acc)' }}
            />
        </div>
    );
}

export function Flag({ kind = 'neutral', children }) {
    return <div className={`cw-flag cw-flag-${kind}`}>{children}</div>;
}

export function Button({ onClick, children, variant = 'ghost' }) {
    return (
        <button type="button" className={`cw-btn cw-btn-${variant}`} onClick={onClick}>
            {children}
        </button>
    );
}

// ── Options probe ────────────────────────────────────────────────────────────
// The question's four MCQ options, made interactive: click one and the widget
// "tries" it on the diagram (via onPick) and reveals what that answer means.
// options = [{ label, note, correct, ...anything the widget needs to enact it }]
export function Options({ options = [], onPick, lead = 'Try each answer' }) {
    const [picked, setPicked] = useState(null);
    if (!options.length) return null;
    const pick = (i) => { setPicked(i); onPick && onPick(options[i], i); };
    const sel = picked != null ? options[picked] : null;
    return (
        <div className="cw-options">
            <div className="cw-options-lead">{lead}</div>
            <div className="cw-options-row">
                {options.map((o, i) => (
                    <button
                        key={i}
                        type="button"
                        className={'cw-opt-btn' + (picked === i ? (o.correct ? ' is-correct' : ' is-wrong') : '')}
                        onClick={() => pick(i)}
                    >
                        <span className="cw-opt-btn-key">{String.fromCharCode(65 + i)}</span>
                        {o.label && o.label !== String.fromCharCode(65 + i)
                            ? <span className="cw-opt-btn-label">{o.label}</span> : null}
                    </button>
                ))}
            </div>
            {sel && (
                <div className={'cw-opt-note ' + (sel.correct ? 'ok' : 'bad')}>
                    <b>{sel.correct ? '✓ Correct — ' : '✗ Not correct — '}</b>{sel.note}
                </div>
            )}
        </div>
    );
}
