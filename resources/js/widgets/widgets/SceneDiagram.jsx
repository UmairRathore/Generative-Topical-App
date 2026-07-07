import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: scene ─────────────────────────────────────────────────────────────
// A generic, per-question interactive diagram engine. Each question authors its OWN
// `scene` that reproduces its exact figure from primitives, adds light animation,
// and wires each MCQ option to draw/highlight something on the diagram. One renderer,
// a unique bespoke diagram per question — no shared/preset figure.
//
// Coordinates: user units inside viewBox [W,H] (default 100×60). y increases DOWNWARD.
// config: {
//   viewBox:[W,H],
//   elements:[ primitive… ],              // the static figure
//   animate:[ {type:'dots', path:[[x,y]…], n?, speed?, color?} ],   // optional motion
//   optionsLead, hint,
//   options:[ {label, correct, note, show:[primitive…]} ]  // drawn on pick, tinted by correct
// }
// primitives (all coords in user units):
//   {type:'line', from:[x,y], to:[x,y], stroke?, width?, dash?, arrow?:'end'|'start'|'both', label?, labelAt?:[x,y]}
//   {type:'rect', x,y,w,h, rotate?(deg,about centre), fill?, stroke?, label?}
//   {type:'circle', cx,cy,r, fill?, stroke?, label?}
//   {type:'arc', cx,cy,r, a0,a1 (deg, 0=east CCW), stroke?, label?}
//   {type:'poly', points:[[x,y]…], fill?, stroke?, close?}
//   {type:'text', x,y, text, color?, size?, align?}
// colors: 'ink' 'faint' 'ok' 'warn' 'bad' 'water' 'metal' or any CSS colour.

const PAL = () => ({ ink: cssVar('--ink-soft', '#A6C4B3'), faint: cssVar('--ink-faint', '#68877A'), ok: cssVar('--ok', '#34D399'), warn: '#fbbf24', bad: '#fb7185', water: '#3EA7E0', metal: '#9aa6b8' });
const col = (c, pal) => pal[c] || c || pal.ink;

export default function SceneDiagram({ config = {}, onReady, onAddToNote }) {
    const { viewBox = [100, 60], elements = [], animate = [] } = config;
    const [picked, setPicked] = useState(null);
    const stRef = useRef(picked); stRef.current = picked;
    const cvRef = useRef(null);

    useEffect(() => { onReady && onReady({ getState: () => ({ picked: picked?.label }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        const [VW, VH] = viewBox; const SC = 12; // px per user unit at base
        let dpr = 1, raf = 0, t0 = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / (VW * SC), h / (VH * SC)); cv.__g = { sc, ox: (w - VW * SC * sc) / 2, oy: (h - VH * SC * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const drawPrim = (ctx, p0, pal, tint) => {
            // Coerce any array-wrapped / string numeric coords an agent may have emitted,
            // so a single malformed field can't break the whole scene.
            const N = (v) => Array.isArray(v) ? (+v[0] || 0) : (typeof v === 'number' ? v : (+v || 0));
            const p = { ...p0 };
            ['cx', 'cy', 'x', 'y', 'w', 'h', 'r', 'a0', 'a1', 'rotate', 'width', 'size'].forEach((k) => { if (p[k] !== undefined) p[k] = N(p[k]); });
            ['from', 'to'].forEach((k) => { if (Array.isArray(p[k])) p[k] = p[k].map(N); });
            if (Array.isArray(p.points)) p.points = p.points.map((pt) => Array.isArray(pt) ? pt.map(N) : pt);
            const X = (x) => x * SC, Y = (y) => y * SC;
            const stroke = tint || col(p.stroke, pal); const width = (p.width || 0.22) * SC;
            ctx.lineWidth = width; ctx.strokeStyle = stroke; ctx.fillStyle = tint || col(p.fill, pal);
            if (p.type === 'line') {
                ctx.setLineDash(p.dash ? [p.dash * SC, p.dash * SC * 0.7] : []);
                ctx.beginPath(); ctx.moveTo(X(p.from[0]), Y(p.from[1])); ctx.lineTo(X(p.to[0]), Y(p.to[1])); ctx.stroke(); ctx.setLineDash([]);
                const a = Math.atan2(Y(p.to[1]) - Y(p.from[1]), X(p.to[0]) - X(p.from[0])), hh = 0.9 * SC;
                const head = (px, py, ang) => { ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(px - hh * Math.cos(ang - 0.4), py - hh * Math.sin(ang - 0.4)); ctx.lineTo(px - hh * Math.cos(ang + 0.4), py - hh * Math.sin(ang + 0.4)); ctx.closePath(); ctx.fill(); };
                if (p.arrow === 'end' || p.arrow === 'both') head(X(p.to[0]), Y(p.to[1]), a);
                if (p.arrow === 'start' || p.arrow === 'both') head(X(p.from[0]), Y(p.from[1]), a + Math.PI);
            } else if (p.type === 'rect') {
                ctx.save(); const cx = X(p.x + p.w / 2), cy = Y(p.y + p.h / 2); ctx.translate(cx, cy); if (p.rotate) ctx.rotate(p.rotate * Math.PI / 180);
                ctx.beginPath(); ctx.rect(-X(p.w) / 2, -Y(p.h) / 2, X(p.w), Y(p.h)); if (p.fill) ctx.fill(); ctx.stroke(); ctx.restore();
            } else if (p.type === 'circle') {
                ctx.beginPath(); ctx.arc(X(p.cx), Y(p.cy), (p.r) * SC, 0, 6.283); if (p.fill) ctx.fill(); ctx.stroke();
            } else if (p.type === 'arc') {
                ctx.beginPath(); ctx.arc(X(p.cx), Y(p.cy), p.r * SC, -p.a1 * Math.PI / 180, -p.a0 * Math.PI / 180); ctx.stroke();
            } else if (p.type === 'poly') {
                ctx.beginPath(); p.points.forEach(([x, y], i) => ctx[i ? 'lineTo' : 'moveTo'](X(x), Y(y))); if (p.close) ctx.closePath(); if (p.fill) ctx.fill(); ctx.stroke();
            } else if (p.type === 'text') {
                ctx.fillStyle = tint || col(p.color, pal); ctx.font = `600 ${(p.size || 1.4) * SC}px Inter, sans-serif`; ctx.textAlign = p.align || 'center'; ctx.textBaseline = 'middle'; ctx.fillText(p.text, X(p.x), Y(p.y));
            }
            if (p.label) { const at = p.labelAt || (p.type === 'line' ? [(p.from[0] + p.to[0]) / 2, (p.from[1] + p.to[1]) / 2 - 1.5] : [p.cx ?? p.x ?? 0, (p.cy ?? p.y ?? 0) - 1.5]); ctx.fillStyle = tint || stroke; ctx.font = `600 ${1.3 * SC}px Inter, sans-serif`; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(p.label, at[0] * SC, at[1] * SC); }
        };

        const draw = (ts) => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            if (!t0) t0 = ts; const frame = (ts - t0) / 1000; const pal = PAL(); const p = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH * SC); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW * SC, VH * SC);
            elements.forEach((el) => drawPrim(ctx, el, pal));
            // animated dots along paths
            animate.forEach((an) => {
                if (an.type !== 'dots' || !an.path || an.path.length < 2) return;
                const nseg = an.path.length - 1, N = an.n || 5, speed = an.speed || 0.4;
                for (let k = 0; k < N; k++) {
                    let t = (frame * speed + k / N) % 1; const seg = Math.min(nseg - 1, Math.floor(t * nseg)); const lt = t * nseg - seg;
                    const [x1, y1] = an.path[seg], [x2, y2] = an.path[seg + 1];
                    ctx.fillStyle = col(an.color, pal) || pal.warn; ctx.beginPath(); ctx.arc((x1 + (x2 - x1) * lt) * SC, (y1 + (y2 - y1) * lt) * SC, 0.42 * SC, 0, 6.283); ctx.fill();
                }
            });
            // picked option overlay
            if (p && p.show) { const tint = p.correct ? pal.ok : pal.bad; p.show.forEach((el) => drawPrim(ctx, el, pal, tint)); }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [viewBox, elements, animate]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: `${viewBox[0]} / ${viewBox[1]}` }}>
                    {config.title && <span className="cw-badge">{config.title}</span>}
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Click each answer'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>Click each answer to see it on the diagram.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this diagram to my notes</button>)}
            </div>
        </div>
    );
}
