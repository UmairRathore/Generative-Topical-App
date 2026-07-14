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
//
// Extensions (all opt-in; legacy configs behave exactly as before):
//   views: [{ id, label, ...any config overrides (xLabel/yLabel/units/curve/
//     read/modes/gradientUnit/…) }] — switchable representations of the same
//     widget instance (e.g. relabel the y-axis) without remounting.
//   view: initial view id.
//   gradientTriangle: true — in gradient mode, replaces the tangent cursor with
//     a two-endpoint rise/run triangle the learner drags along the line; shows
//     Δy, Δx and Δy/Δx.
//   gradientQuantity / gradientUnit: what the gradient IS on these axes and its
//     displayed unit (explicit config — never derived by naive concatenation).
//   areaAdjustable: true — in area mode, the learner drags both interval bounds;
//     the readout shows the area between them.
//   areaQuantity / areaUnit: what the accumulated area means and its unit.
//   State (onReady → getState/setState) covers read, cx, viewId,
//   gradientPoints and areaInterval.

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
const areaUnder = (c, x, n = 240) => { let a = 0, dx = x / n; if (!dx) return 0; for (let i = 0; i < n; i++) a += (curveY(c, i * dx) + curveY(c, (i + 1) * dx)) / 2 * dx; return a; };
const gradAt = (c, x, h = 1e-3) => (curveY(c, Math.min(x + h, 1e9)) - curveY(c, Math.max(x - h, 0))) / (2 * h);
const fmt = (v) => (Math.abs(v) >= 1000 || (v !== 0 && Math.abs(v) < 0.01)) ? v.toExponential(1) : String(Number(v.toPrecision(3)));

export default function GraphExplorer({ config = {}, onReady, onAddToNote }) {
    const views = Array.isArray(config.views) && config.views.length > 0 ? config.views : null;
    const [viewId, setViewId] = useState(config.view || (views ? views[0].id : null));
    const activeView = views ? (views.find((v) => v.id === viewId) || views[0]) : null;
    const cfg = activeView ? { ...config, ...activeView } : config;

    const { title, xLabel = 'x', xUnit = '', xMax = 10, yLabel = 'y', yUnit = '', yMax = 10, yMin = 0, curve = { type: 'linear', m: 1, c: 0 } } = cfg;
    const triangle = !!cfg.gradientTriangle;
    const adjustableArea = !!cfg.areaAdjustable;
    const [read, setRead] = useState(cfg.read || 'value');
    const [cx, setCx] = useState(cfg.startAt != null ? cfg.startAt : xMax * 0.55);
    const [gpts, setGpts] = useState(() => cfg.triangleAt || [xMax * 0.25, xMax * 0.75]);
    const [aiv, setAiv] = useState(() => cfg.areaAt || [0, xMax * 0.6]);
    const [probe, setProbe] = useState(null);
    const [pickedCurve, setPickedCurve] = useState(null);   // "which graph" compare mode
    const compare = !!cfg.compare || (cfg.options || []).some((o) => o.curve);
    const cvRef = useRef(null);
    const geomRef = useRef({ scale: 1, ox: 0, oy: 0, pad: {} });
    const dragRef = useRef(null); // null | 'cx' | 'g0' | 'g1' | 'a0' | 'a1'
    const stRef = useRef({}); stRef.current = { read, cx, gpts, aiv, triangle, adjustableArea, xMax };

    useEffect(() => {
        onReady && onReady({
            getState: () => ({ ...config, read, cx, viewId, gradientPoints: gpts, areaInterval: aiv }),
            setState: (s) => {
                if (s?.viewId != null) setViewId(s.viewId);
                if (s?.read) setRead(s.read);
                if (s?.cx != null) setCx(s.cx);
                if (Array.isArray(s?.gradientPoints)) setGpts(s.gradientPoints);
                if (Array.isArray(s?.areaInterval)) setAiv(s.areaInterval);
            },
        });
    }, [onReady, read, cx, viewId, gpts, aiv]); // eslint-disable-line

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

    const triSlope = (curveY(curve, gpts[1]) - curveY(curve, gpts[0])) / ((gpts[1] - gpts[0]) || 1);
    const value = read === 'area'
        ? (adjustableArea ? areaUnder(curve, aiv[1]) - areaUnder(curve, aiv[0]) : areaUnder(curve, cx))
        : read === 'gradient'
            ? (triangle ? triSlope : gradAt(curve, cx))
            : read === 'halflife' ? halfLife : cy;
    const defaultGradientUnit = `${yUnit}${xUnit ? '/' + xUnit : ''}`;
    const readMeta = {
        value: { label: `${yLabel} at this point`, unit: yUnit },
        gradient: {
            label: cfg.gradientQuantity ? `${cfg.gradientQuantity} = Δ${yLabel} / Δ${xLabel}` : `gradient (${cfg.gradientUnit ?? defaultGradientUnit})`,
            unit: cfg.gradientQuantity ? (cfg.gradientUnit ?? defaultGradientUnit) : '',
        },
        area: { label: cfg.areaQuantity ?? 'area under the line', unit: cfg.areaUnit ?? `${yUnit}${xUnit ? '·' + xUnit : ''}` },
        halflife: { label: 'half-life (count halves)', unit: xUnit },
    }[read] || { label: read, unit: '' };
    const readSub = read === 'gradient' && triangle
        ? <>Δ{yLabel} = <b>{fmt(curveY(curve, gpts[1]) - curveY(curve, gpts[0]))} {yUnit}</b> · Δ{xLabel} = <b>{fmt(gpts[1] - gpts[0])} {xUnit}</b></>
        : read === 'area' && adjustableArea
            ? <>from {xLabel} = <b>{fmt(aiv[0])}</b> to <b>{fmt(aiv[1])} {xUnit}</b></>
            : <>at {xLabel} = <b>{fmt(cx)} {xUnit}</b></>;

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
        if (cfg.points) {
            ctx.strokeStyle = ink; ctx.lineWidth = 2.2; const s = 5;
            cfg.points.forEach(([x, y]) => { const px = X(x), py = Y(y); ctx.beginPath(); ctx.moveTo(px - s, py - s); ctx.lineTo(px + s, py + s); ctx.moveTo(px + s, py - s); ctx.lineTo(px - s, py + s); ctx.stroke(); });
        }

        // in compare mode draw the clicked option's candidate curve; otherwise the base curve
        const shown = compare ? pickedCurve : curve;

        // area shading (normal mode only)
        if (!compare && read === 'area') {
            const [a0, a1] = adjustableArea ? aiv : [0, cx];
            ctx.fillStyle = 'rgba(52,211,153,.16)'; ctx.beginPath(); ctx.moveTo(X(a0), Y(0));
            for (let i = 0; i <= 120; i++) { const x = a0 + ((a1 - a0) * i) / 120; ctx.lineTo(X(x), Y(curveY(curve, x))); }
            ctx.lineTo(X(a1), Y(0)); ctx.closePath(); ctx.fill();
        }
        // the line
        if (shown) {
            ctx.strokeStyle = (compare && probe) ? (probe.correct ? acc : '#fb7185') : acc;
            ctx.lineWidth = 2.6; ctx.beginPath();
            for (let i = 0; i <= 160; i++) { const x = (xMax * i) / 160, px = X(x), py = Y(curveY(shown, x)); i ? ctx.lineTo(px, py) : ctx.moveTo(px, py); }
            ctx.stroke();
        }

        if (!compare) {
            if (read === 'gradient' && triangle) {
                // rise/run gradient triangle between two draggable points on the line
                const [g0, g1] = gpts, y0 = curveY(curve, g0), y1 = curveY(curve, g1);
                ctx.strokeStyle = '#fbbf24'; ctx.lineWidth = 2; ctx.setLineDash([5, 4]);
                ctx.beginPath(); ctx.moveTo(X(g0), Y(y0)); ctx.lineTo(X(g1), Y(y0)); ctx.lineTo(X(g1), Y(y1)); ctx.stroke(); ctx.setLineDash([]);
                ctx.strokeStyle = '#fbbf24'; ctx.lineWidth = 2.4;
                ctx.beginPath(); ctx.moveTo(X(g0), Y(y0)); ctx.lineTo(X(g1), Y(y1)); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 11px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(`Δ${xLabel} = ${fmt(g1 - g0)}`, X((g0 + g1) / 2), Y(y0) + (y1 >= y0 ? 16 : -8));
                ctx.textAlign = y1 >= y0 ? 'left' : 'right';
                ctx.fillText(`Δ${yLabel} = ${fmt(y1 - y0)}`, X(g1) + (y1 >= y0 ? 8 : -8), Y((y0 + y1) / 2) + 4);
                [[g0, y0], [g1, y1]].forEach(([gx, gy]) => {
                    ctx.fillStyle = '#fbbf24'; ctx.strokeStyle = '#fff'; ctx.lineWidth = 2;
                    ctx.beginPath(); ctx.arc(X(gx), Y(gy), 7, 0, 6.283); ctx.fill(); ctx.stroke();
                });
            } else if (read === 'gradient') {
                // gradient tangent
                const g = gradAt(curve, cx), y0 = curveY(curve, cx), dx = xMax * 0.16;
                ctx.strokeStyle = '#fbbf24'; ctx.lineWidth = 2; ctx.setLineDash([5, 4]);
                ctx.beginPath(); ctx.moveTo(X(cx - dx), Y(y0 - g * dx)); ctx.lineTo(X(cx + dx), Y(y0 + g * dx)); ctx.stroke(); ctx.setLineDash([]);
            }
            if (read === 'area' && adjustableArea) {
                // two draggable interval bounds
                aiv.forEach((ax) => {
                    ctx.strokeStyle = 'rgba(251,191,36,.7)'; ctx.lineWidth = 1.5; ctx.setLineDash([3, 3]);
                    ctx.beginPath(); ctx.moveTo(X(ax), pad.t); ctx.lineTo(X(ax), pad.t + gh); ctx.stroke(); ctx.setLineDash([]);
                    ctx.fillStyle = '#fbbf24'; ctx.strokeStyle = '#fff'; ctx.lineWidth = 2;
                    ctx.beginPath(); ctx.arc(X(ax), Y(0), 7, 0, 6.283); ctx.fill(); ctx.stroke();
                });
            }
            const plainCursor = !(read === 'gradient' && triangle) && !(read === 'area' && adjustableArea);
            if (plainCursor) {
                // cursor
                ctx.strokeStyle = 'rgba(251,191,36,.6)'; ctx.lineWidth = 1.5; ctx.setLineDash([3, 3]);
                ctx.beginPath(); ctx.moveTo(X(cx), pad.t); ctx.lineTo(X(cx), pad.t + gh); ctx.stroke(); ctx.setLineDash([]);
                ctx.fillStyle = '#fbbf24'; ctx.strokeStyle = '#fff'; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.arc(X(cx), Y(cy), 6, 0, 6.283); ctx.fill(); ctx.stroke();
            }
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
        if (cfg.markX) {
            const mx = X(cfg.markX.x), my = Y(cfg.markX.y);
            ctx.fillStyle = '#fbbf24'; ctx.strokeStyle = '#fff'; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.arc(mx, my, 6, 0, 6.283); ctx.fill(); ctx.stroke();
            ctx.fillStyle = ink; ctx.font = '700 17px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText(cfg.markX.label || 'X', mx, my - 14);
        }
    };
    useEffect(() => { draw(); }, [cx, read, config, viewId, gpts, aiv, pickedCurve, probe]); // eslint-disable-line
    useEffect(() => { const cv = cvRef.current; const ro = new ResizeObserver(draw); ro.observe(cv.parentElement); return () => ro.disconnect(); }, []); // eslint-disable-line

    // drag: plain cursor, triangle endpoints, or area interval bounds
    useEffect(() => {
        const cv = cvRef.current;
        const toX = (clientX) => { const r = cv.getBoundingClientRect(), { pad, gw } = geomRef.current; const { xMax: xm } = stRef.current; return Math.max(0, Math.min(xm, ((clientX - r.left - (pad?.l || 0)) / (gw || 1)) * xm)); };
        const snap = (x) => { const { xMax: xm } = stRef.current; return Math.round(x / (xm / 40)) * (xm / 40); };
        const apply = (clientX) => {
            const x = snap(toX(clientX));
            const { xMax: xm } = stRef.current;
            const gap = xm / 20;
            const t = dragRef.current;
            if (t === 'g0') setGpts(([, g1]) => [Math.min(x, g1 - gap), g1]);
            else if (t === 'g1') setGpts(([g0]) => [g0, Math.max(x, g0 + gap)]);
            else if (t === 'a0') setAiv(([, a1]) => [Math.min(x, a1 - gap), a1]);
            else if (t === 'a1') setAiv(([a0]) => [a0, Math.max(x, a0 + gap)]);
            else setCx(x);
        };
        const down = (e) => {
            const x = toX(e.clientX);
            const { read: rd, gpts: g, aiv: a, triangle: tri, adjustableArea: adj } = stRef.current;
            if (rd === 'gradient' && tri) dragRef.current = Math.abs(x - g[0]) <= Math.abs(x - g[1]) ? 'g0' : 'g1';
            else if (rd === 'area' && adj) dragRef.current = Math.abs(x - a[0]) <= Math.abs(x - a[1]) ? 'a0' : 'a1';
            else dragRef.current = 'cx';
            apply(e.clientX);
        };
        const move = (e) => { if (dragRef.current) { e.preventDefault(); apply(e.clientX); } };
        const up = () => { dragRef.current = null; };
        cv.addEventListener('pointerdown', down); window.addEventListener('pointermove', move); window.addEventListener('pointerup', up);
        return () => { cv.removeEventListener('pointerdown', down); window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); };
    }, []);

    const flagText = compare
        ? <>Click each answer to draw its graph over the data — only one line passes through all the points.</>
        : read === 'gradient' && triangle
            ? <>Drag the two corners of the triangle along the line. The triangle shows Δ{yLabel} and Δ{xLabel}; their ratio is the gradient.</>
            : read === 'area' && adjustableArea
                ? <>Drag either end of the shaded interval. The readout shows the area under the line between them.</>
                : <>Drag the dot along the line. <b>value</b> reads {yLabel}; <b>gradient</b> is the slope; <b>area</b> is the region under the line.</>;

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 11' }}>
                    <span className="cw-badge">{cfg.badge || title || 'Drag to read the graph'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {views && (
                    <div className="cw-btnrow">
                        {views.map((v) => (
                            <button key={v.id} className={'cw-btn ' + (v.id === (activeView?.id) ? 'cw-btn-save' : 'cw-btn-ghost')}
                                    onClick={() => { setViewId(v.id); if (v.read) setRead(v.read); setCx((c) => Math.min(c, v.xMax ?? xMax)); }}>
                                {v.label}
                            </button>
                        ))}
                    </div>
                )}
                {!compare && (
                    <>
                        <Stat label={readMeta.label} value={`${fmt(value)}`} unit={readMeta.unit} tone="acc" sub={readSub} />
                        {(cfg.modes || ['value', 'gradient', 'area']).length > 1 && (
                            <div className="cw-btnrow">
                                {(cfg.modes || ['value', 'gradient', 'area']).map((m) => (
                                    <button key={m} className={'cw-btn ' + (read === m ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setRead(m)}>{m}</button>
                                ))}
                            </div>
                        )}
                    </>
                )}
                {cfg.options?.length > 0 && (
                    <Options options={cfg.options} lead={cfg.optionsLead || 'What does each answer describe?'}
                             onPick={(o) => { setProbe(o); if (o.curve) setPickedCurve(o.curve); else if (o.enact) setRead(o.enact); }} />
                )}
                <Flag kind="neutral">{flagText}</Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, read, cx })}>📌 Save this graph to my notes</button>
                )}
            </div>
        </div>
    );
}
