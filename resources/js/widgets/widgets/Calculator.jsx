import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Slider, Stat, Button } from '../primitives.jsx';
import { evalFormula } from '../lib/formula.js';

// ── Calculator archetype ─────────────────────────────────────────────────────
// The single biggest widget family (~31% of interactive questions): "plug the
// given quantities into a formula and read off the answer". Every such question
// is ONE `config` — no bespoke code:
//
//   {
//     subject, prompt, symbol, formula,               // "n", "mass / mr"
//     inputs: [{ key, label, unit, value, min?, max?, step?, editable? }],
//     result: { label, unit, precision },
//     options: [{ label, value }], answer               // MCQ (optional)
//   }
//
// Editable inputs become live sliders; the result recomputes as you drag and the
// matching MCQ option lights up — so a student can explore "what if the mass were
// bigger?", not just see one static answer. Notes-ready via onReady/onAddToNote.

function fmt(x, p = 3) {
    if (!isFinite(x)) return '—';
    if (x !== 0 && (Math.abs(x) >= 1e5 || Math.abs(x) < 1e-3)) return x.toExponential(2);
    return String(Number(x.toPrecision(p)));
}

export default function Calculator({ config = {}, onReady, onAddToNote }) {
    const {
        subject, prompt = '', symbol = 'x', formula = '',
        inputs = [], result = {}, options = [], answer = null,
    } = config;

    const editable = useMemo(
        () => inputs.filter((i) => i.editable !== false && i.min != null && i.max != null),
        [inputs],
    );

    const init = useMemo(() => {
        const base = Object.fromEntries(inputs.map((i) => [i.key, i.value]));
        if (config._state?.vals) Object.assign(base, config._state.vals);
        return base;
    }, [inputs]); // eslint-disable-line react-hooks/exhaustive-deps
    const [vals, setVals] = useState(init);
    const [picked, setPicked] = useState(null);   // options probe: which answer was clicked

    const set = useCallback((k, v) => setVals((s) => ({ ...s, [k]: v })), []);
    const reset = useCallback(() => setVals(init), [init]);

    const computed = useMemo(() => {
        try { return evalFormula(formula, vals); } catch { return NaN; }
    }, [formula, vals]);

    // Which MCQ option the current result lands on (within ~1.5%).
    const matchIdx = useMemo(() => {
        let best = -1, bestErr = 0.015;
        options.forEach((o, i) => {
            const err = Math.abs(o.value - computed) / (Math.abs(o.value) || 1);
            if (err < bestErr) { bestErr = err; best = i; }
        });
        return best;
    }, [options, computed]);

    // Working line with the current numbers substituted in.
    const working = useMemo(() => {
        let s = formula;
        inputs.forEach((i) => { s = s.replace(new RegExp('\\b' + i.key + '\\b', 'g'), fmt(vals[i.key], 4)); });
        return s;
    }, [formula, inputs, vals]);

    // ── Notes contract (dormant until a host wires it) ───────────────────────
    const valsRef = useRef(vals);
    valsRef.current = vals;
    useEffect(() => {
        if (!onReady) return;
        onReady({
            getState: () => ({ ...config, _state: { vals: valsRef.current } }),
            setState: (s) => { if (s?._state?.vals) setVals(s._state.vals); },
        });
    }, [onReady]); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <div className="cw-calc">
            <div className="cw-calc-main">
                {subject ? <div className="cw-badge">{subject}</div> : null}
                {prompt ? <p className="cw-calc-prompt">{prompt}</p> : null}

                <div className="cw-calc-givens">
                    {inputs.map((i) => (
                        <span className="cw-chip" key={i.key}>
                            {i.label}
                            <b>{fmt(vals[i.key], 4)}{i.unit ? ' ' + i.unit : ''}</b>
                        </span>
                    ))}
                </div>

                {editable.length > 0 && (
                    <div className="cw-calc-sliders">
                        {editable.map((i) => (
                            <Slider
                                key={i.key}
                                label={i.label}
                                value={vals[i.key]}
                                min={i.min}
                                max={i.max}
                                step={i.step ?? 1}
                                onChange={(v) => set(i.key, v)}
                                format={(v) => fmt(v, 4) + (i.unit ? ' ' + i.unit : '')}
                            />
                        ))}
                        <Button variant="ghost" onClick={reset}>↺ Reset to question values</Button>
                    </div>
                )}
            </div>

            <div className="cw-calc-side">
                <div className="cw-calc-formula">
                    <div className="cw-calc-flabel">Working</div>
                    <div className="cw-calc-expr">{symbol} = {formula}</div>
                    <div className="cw-calc-expr cw-calc-sub">{symbol} = {working}</div>
                </div>

                <Stat
                    label={result.label || 'Result'}
                    value={fmt(computed, result.precision || 3)}
                    unit={result.unit}
                    tone="acc"
                />

                {options.length > 0 && (
                    <>
                        <div className="cw-calc-options">
                            {options.map((o, i) => {
                                const correct = o.correct != null ? o.correct : i === answer;
                                const isMatch = i === matchIdx;
                                const isPicked = i === picked;
                                const cls = ['cw-opt', 'cw-opt-btn2', isPicked ? (correct ? 'is-answer' : 'is-yours') : '', isMatch && !isPicked ? 'is-match' : ''].join(' ');
                                return (
                                    <button type="button" className={cls} key={i} onClick={() => setPicked(i)}>
                                        <span className="cw-opt-key">{String.fromCharCode(65 + i)}</span>
                                        <span className="cw-opt-val">{o.label}</span>
                                        {isMatch && !isPicked ? <span className="cw-opt-tag">your value</span> : null}
                                        {isPicked ? <span className={'cw-opt-tag' + (correct ? ' ans' : '')}>{correct ? 'correct' : 'not correct'}</span> : null}
                                    </button>
                                );
                            })}
                        </div>
                        {picked != null && options[picked]?.note && (() => {
                            const ok = options[picked].correct != null ? options[picked].correct : picked === answer;
                            return (
                                <div className={'cw-opt-note ' + (ok ? 'ok' : 'bad')}>
                                    <b>{ok ? '✓ Correct — ' : '✗ Not correct — '}</b>{options[picked].note}
                                </div>
                            );
                        })()}
                    </>
                )}

                {onAddToNote && (
                    <button
                        type="button"
                        className="cw-btn cw-btn-save"
                        onClick={() => onAddToNote({ ...config, _state: { vals } })}
                    >
                        ＋ Save to my notes
                    </button>
                )}
            </div>
        </div>
    );
}
