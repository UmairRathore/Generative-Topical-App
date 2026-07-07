import React, { useEffect, useMemo, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag, Options } from '../primitives.jsx';

// ── Widget: graph_explorer ───────────────────────────────────────────────────
// A general line-graph read-off. Plots a curve and lets the student drag a
// cursor to read the VALUE at a point, the GRADIENT (tangent), or the AREA under
// the line — the three things Cambridge graph questions turn on. Clicking an MCQ
// option switches the read-off to what that answer refers to, so a wrong answer
// is shown to be (e.g.) "the gradient, not the area".
// Covers: speed–time, force–extension (Hooke), wave graphs, half-life decay.
//
// config: { title, xLabel,xUnit,xMax, yLabel,yUnit,yMax, curve, read, options }
//   curve: { type:'piecewise', points:[[x,y]…] } | { type:'linear', m,c }
//        | { type:'proportional', k } | { type:'exp', y0,halfLife }
//        | { type:'sine', amplitude,wavelength,phase }
//   read: 'area' | 'gradient' | 'value'
//   options[i].enact: 'area'|'gradient'|'value' — what clicking that answer shows

function curveY(c, x) {
    switch (c.type) {
        case 'linear': return c.m * x + (c.c || 0);
        case 'proportional': return c.k * x;
        case 'exp': return c.y0 * Math.pow(0.5, x / c.halfLife);
        case 'sine': return c.amplitude * Math.sin((2 * Math.PI * x) / c.wavelength + (c.phase || 0));
        case 'piecewise': {
            const p = c.points;
            for (let i = 0; i < p.length - 1; i++) {
                const [x1, y1] = p[i], [x2, y2] = p[i + 1];
                if (x >= x1 && x <= x2) { const t = (x - x1) / ((x2 - x1) || 1); return y1 + t * (y2 - y1); }
            }
            return p[p.length - 1][1];
        }
        default: return 0;
    }
}
const areaUnder = (c, x, n = 240) => { let a = 0, dx = x / n; for (let i = 0; i < n; i++) a += (curveY(c, i * dx) + curveY(c, (i + 1) * dx)) / 2 * dx; return a; };
const gradAt = (c, x, h = 1e-3) => (curveY(c, Math.min(x + h, 1e9)) - curveY(c, Math.max(x - h, 0))) / (2 * h);
const fmt = (v) => (Math.abs(v) >= 1000 || (v !== 0 && Math.abs(v) < 0.01)) ? v.toExponential(1) : String(Number(v.toPrecision(3)));

export default function GraphExplorer({ config = {}, onReady, onAddToNote }) {
    const { title, xLabel = 'x', xUnit = '', xMax = 10, yLabel = 'y', yUnit = '', yMax = 10, yMin = 0, curve = { type: 'linear', m: 1, c: 0 } } = config;
    const [read, setRead] = useState(config.read || 'value');
    const [cx, setCx] = useState(config.startAt != null ? config.startAt : xMax * 0.55);
    const [probe, setProbe] = useState(null);
    const [pickedCurve, setPickedCurve] = useState(null);   // "which graph" compare mode
    const compare = !!config.compare || (config.options || []).some((o) => o.curve);
    const cvRef = useRef(null);
    const geomRef = useRef({ scale: 1, ox: 0, oy: 0, pad: {} });
    const dragRef = useRef(false);
    const stRef = useRef({ read, cx }); stRef.current = { read, cx };

    useEffect(() => { onReady && onReady({ getState: () => ({ ...config, read, cx }), setState: (s) => { if (s?.read) setRead(s.read); if (s?.cx != null) setCx(s.cx); } }); }, [onReady]); // eslint-disable-line

    const cy = curveY(curve, cx);
    const halfLife = useMemo(() => {
        const y0 = curveY(curve, 0), tgt = y0 / 2, n = 800;
        let prev = 0;
        for (let i = 1; i <= n; i++) {
            const x = (xMax * i) / n, yx = curveY(curve, x);
            if (yx <= tgt) { const yp = curveY(curve, prev); return yp === yx ? x : prev + ((tgt - yp) / (yx - yp)) * (x - prev); }
            prev = x;
        }
        return NaN;
    }, [curve, xMax]);
    const value = read === 'area' ? areaUnder(curve, cx) : read === 'gradient' ? gradAt(curve, cx) : read === 'halflife' ? halfLife : cy;
    const readMeta = {
        value: { label: `${yLabel} at this point`, unit: yUnit },
        gradient: { label: `gradient (${yUnit}${xUnit ? '/' + xUnit : ''})`, unit: '' },
        area: { label: `area under the line`, unit: config.areaUnit ?? `${yUnit}${xUnit ? '·' + xUnit : ''}` },
        halflife: { label: 'half-life (count halves)', unit: xUnit },
    }[read] || { label: read, unit: '' };

    // draw
    const draw = () => {
        const cv = cvRef.current, host = cv.parentElement; if (!cv || !host) return;
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return;
        cv.width = w * dpr; cv.height = h * dpr; const ctx = cv.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
        const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
        const pad = { l: 52, r: 18, t: 18, b: 40 }, gw = w - pad.l - pad.r, gh = h - pad.t - pad.b;
        const X = (x) => pad.l + (x / xMax) * gw, Y = (y) => pad.t + gh - ((Math.max(yMin, Math.min(yMax, y)) - yMin) / (yMax - yMin)) * gh;
        geomRef.current = { pad, gw, gh };

        // grid
        ctx.strokeStyle = line; ctx.lineWidth = 1;
        for (let i = 0; i <= 5; i++) { const gy = pad.t + (gh * i) / 5; ctx.globalAlpha = .5; ctx.beginPath(); ctx.moveTo(pad.l, gy); ctx.lineTo(pad.l + gw, gy); ctx.stroke(); }
        for (let i = 0; i <= 5; i++) { const gx = pad.l + (gw * i) / 5; ctx.beginPath(); ctx.moveTo(gx, pad.t); ctx.lineTo(gx, pad.t + gh); ctx.stroke(); }
        ctx.globalAlpha = 1;
        // axes
        ctx.strokeStyle = ink; ctx.lineWidth = 1.5; ctx.beginPath(); ctx.moveTo(pad.l, pad.t); ctx.lineTo(pad.l, pad.t + gh); ctx.lineTo(pad.l + gw, pad.t + gh); ctx.stroke();
        // zero baseline when the y-axis spans negative values
        if (yMin < 0) { ctx.strokeStyle = ink; ctx.lineWidth = 1.5; ctx.beginPath(); ctx.moveTo(pad.l, Y(0)); ctx.lineTo(pad.l + gw, Y(0)); ctx.stroke(); ctx.fillStyle = faint; ctx.font = '600 11px "JetBrains Mono", monospace'; ctx.textAlign = 'right'; ctx.fillText('0', pad.l - 6, Y(0) + 4); }
        ctx.fillStyle = faint; ctx.font = '600 11px Inter, sans-serif'; ctx.textAlign = 'center';
        ctx.fillText(`${xLabel}${xUnit ? ' / ' + xUnit : ''} →`, pad.l + gw / 2, h - 8);
        ctx.save(); ctx.translate(13, pad.t + gh / 2); ctx.rotate(-Math.PI / 2); ctx.fillText(`${yLabel}${yUnit ? ' / ' + yUnit : ''} →`, 0, 0); ctx.restore();

        // measured data points (crosses) — for "which graph fits?" questions
        if (config.points) {
            ctx.strokeStyle = ink; ctx.lineWidth = 2.2; const s = 5;
            config.points.forEach(([x, y]) => { const px = X(x), py = Y(y); ctx.beginPath(); ctx.moveTo(px - s, py - s); ctx.lineTo(px + s, py + s); ctx.moveTo(px + s, py - s); ctx.lineTo(px - s, py + s); ctx.stroke(); });
        }

        // in compare mode draw the clicked option's candidate curve; otherwise the base curve
        const shown = compare ? pickedCurve : curve;

        // area shading (normal mode only)
        if (!compare && read === 'area') {
            ctx.fillStyle = 'rgba(52,211,153,.16)'; ctx.beginPath(); ctx.moveTo(X(0), Y(0));
            for (let i = 0; i <= 120; i++) { const x = (cx * i) / 120; ctx.lineTo(X(x), Y(curveY(curve, x))); }
            ctx.lineTo(X(cx), Y(0)); ctx.closePath(); ctx.fill();
        }
        // the line
        if (shown) {
            ctx.strokeStyle = (compare && probe) ? (probe.correct ? acc : '#fb7185') : acc;
            ctx.lineWidth = 2.6; ctx.beginPath();
            for (let i = 0; i <= 160; i++) { const x = (xMax * i) / 160, px = X(x), py = Y(curveY(shown, x)); i ? ctx.lineTo(px, py) : ctx.moveTo(px, py); }
            ctx.stroke();
        }

        if (!compare) {
            // gradient tangent
            if (read === 'gradient') {
                const g = gradAt(curve, cx), y0 = curveY(curve, cx), dx = xMax * 0.16;
                ctx.strokeStyle = '#fbbf24'; ctx.lineWidth = 2; ctx.setLineDash([5, 4]);
                ctx.beginPath(); ctx.moveTo(X(cx - dx), Y(y0 - g * dx)); ctx.lineTo(X(cx + dx), Y(y0 + g * dx)); ctx.stroke(); ctx.setLineDash([]);
            }
            // cursor
            ctx.strokeStyle = 'rgba(251,191,36,.6)'; ctx.lineWidth = 1.5; ctx.setLineDash([3, 3]);
            ctx.beginPath(); ctx.moveTo(X(cx), pad.t); ctx.lineTo(X(cx), pad.t + gh); ctx.stroke(); ctx.setLineDash([]);
            ctx.fillStyle = '#fbbf24'; ctx.strokeStyle = '#fff'; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.arc(X(cx), Y(cy), 6, 0, 6.283); ctx.fill(); ctx.stroke();
            // half-life guides
            if (read === 'halflife' && isFinite(halfLife)) {
                const y0 = curveY(curve, 0), tgt = y0 / 2;
                ctx.strokeStyle = 'rgba(251,191,36,.75)'; ctx.lineWidth = 1.5; ctx.setLineDash([4, 4]);
                ctx.beginPath(); ctx.moveTo(X(0), Y(tgt)); ctx.lineTo(X(halfLife), Y(tgt)); ctx.lineTo(X(halfLife), Y(0)); ctx.stroke(); ctx.setLineDash([]);
                ctx.fillStyle = '#fbbf24';
                ctx.beginPath(); ctx.arc(X(0), Y(y0), 5, 0, 6.283); ctx.fill();
                ctx.beginPath(); ctx.arc(X(halfLife), Y(tgt), 6, 0, 6.283); ctx.fill();
            }
        }
        // marked point (e.g. the limit of proportionality)
        if (config.markX) {
            const mx = X(config.markX.x), my = Y(config.markX.y);
            ctx.fillStyle = '#fbbf24'; ctx.strokeStyle = '#fff'; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.arc(mx, my, 6, 0, 6.283); ctx.fill(); ctx.stroke();
            ctx.fillStyle = ink; ctx.font = '700 17px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText(config.markX.label || 'X', mx, my - 14);
        }
    };
    useEffect(() => { draw(); }, [cx, read, config, pickedCurve, probe]); // eslint-disable-line
    useEffect(() => { const cv = cvRef.current; const ro = new ResizeObserver(draw); ro.observe(cv.parentElement); return () => ro.disconnect(); }, []); // eslint-disable-line

    // drag cursor
    useEffect(() => {
        const cv = cvRef.current;
        const toX = (clientX) => { const r = cv.getBoundingClientRect(), { pad, gw } = geomRef.current; return Math.max(0, Math.min(xMax, ((clientX - r.left - (pad?.l || 0)) / (gw || 1)) * xMax)); };
        const set = (clientX) => setCx(Math.round(toX(clientX) / (xMax / 40)) * (xMax / 40));
        const down = (e) => { dragRef.current = true; set(e.clientX); };
        const move = (e) => { if (dragRef.current) { e.preventDefault(); set(e.clientX); } };
        const up = () => { dragRef.current = false; };
        cv.addEventListener('pointerdown', down); window.addEventListener('pointermove', move); window.addEventListener('pointerup', up);
        return () => { cv.removeEventListener('pointerdown', down); window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); };
    }, [xMax]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 11' }}>
                    <span className="cw-badge">{title || 'Drag to read the graph'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {!compare && (
                    <>
                        <Stat label={readMeta.label} value={`${fmt(value)}`} unit={readMeta.unit} tone="acc"
                              sub={<>at {xLabel} = <b>{fmt(cx)} {xUnit}</b></>} />
                        <div className="cw-btnrow">
                            {(config.modes || ['value', 'gradient', 'area']).map((m) => (
                                <button key={m} className={'cw-btn ' + (read === m ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setRead(m)}>{m}</button>
                            ))}
                        </div>
                    </>
                )}
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'What does each answer describe?'}
                             onPick={(o) => { setProbe(o); if (o.curve) setPickedCurve(o.curve); else if (o.enact) setRead(o.enact); }} />
                )}
                <Flag kind="neutral">
                    {compare
                        ? <>Click each answer to draw its graph over the data — only one line passes through all the points.</>
                        : <>Drag the dot along the line. <b>value</b> reads {yLabel}; <b>gradient</b> is the slope; <b>area</b> is the region under the line.</>}
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, read, cx })}>📌 Save this graph to my notes</button>
                )}
            </div>
        </div>
    );
}
