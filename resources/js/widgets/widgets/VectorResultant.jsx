import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: vector_resultant ──────────────────────────────────────────────────
// Two vectors (usually perpendicular forces) from a common origin; the widget
// draws them, the parallelogram, and — on picking an option — the candidate
// resultant, with the computed magnitude √(v1²+v2²) and angle. Each MCQ option is
// a candidate resultant DIRECTION (8-way compass); only the outward diagonal one
// is correct. Covers "which diagram shows the resultant" and "find the resultant".
// config: {
//   v1:{dir, mag, label}, v2:{dir, mag, label}, unit,
//   optionsLead, hint, options:[{label, dir, correct, note}]
// }

const VW = 780, VH = 560;
// 8-way unit directions in canvas space (y points DOWN, so 'up' is -y)
const DIR = {
    right: [1, 0], left: [-1, 0], up: [0, -1], down: [0, 1],
    'up-right': [0.7071, -0.7071], 'up-left': [-0.7071, -0.7071],
    'down-right': [0.7071, 0.7071], 'down-left': [-0.7071, 0.7071],
};

export default function VectorResultant({ config = {}, onReady, onAddToNote }) {
    const { v1 = { dir: 'right', mag: 4, label: '4 N' }, v2 = { dir: 'up', mag: 3, label: '3 N' }, unit = 'N' } = config;
    const [picked, setPicked] = useState(null);
    const stRef = useRef(picked); stRef.current = picked;
    const cvRef = useRef(null);
    const R = Math.hypot(v1.mag, v2.mag);

    useEffect(() => { onReady && onReady({ getState: () => ({ picked: picked?.label }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const arrow = (ox, oy, dx, dy, len, col, lw, label) => {
            const ex = ox + dx * len, ey = oy + dy * len, a = Math.atan2(dy, dx), h = 15;
            ctx.strokeStyle = col; ctx.lineWidth = lw; ctx.beginPath(); ctx.moveTo(ox, oy); ctx.lineTo(ex, ey); ctx.stroke();
            ctx.fillStyle = col; ctx.beginPath(); ctx.moveTo(ex, ey); ctx.lineTo(ex - h * Math.cos(a - 0.4), ey - h * Math.sin(a - 0.4)); ctx.lineTo(ex - h * Math.cos(a + 0.4), ey - h * Math.sin(a + 0.4)); ctx.closePath(); ctx.fill();
            if (label) { ctx.fillStyle = col; ctx.font = '600 15px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(label, ex + dx * 20, ey + dy * 20 + 4); }
            return [ex, ey];
        };

        const draw = () => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399'), bad = '#fb7185', warn = '#fbbf24';
            const p = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            const ox = VW * 0.36, oy = VH * 0.58, scale = 34; // px per unit
            // grid
            ctx.strokeStyle = 'rgba(166,196,179,.08)'; ctx.lineWidth = 1;
            for (let x = 0; x < VW; x += scale) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, VH); ctx.stroke(); }
            for (let y = 0; y < VH; y += scale) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(VW, y); ctx.stroke(); }
            ctx.fillStyle = ink; ctx.beginPath(); ctx.arc(ox, oy, 4, 0, 6.283); ctx.fill();

            const d1 = DIR[v1.dir] || DIR.right, d2 = DIR[v2.dir] || DIR.up;
            const e1 = arrow(ox, oy, d1[0], d1[1], v1.mag * scale, warn, 3, v1.label);
            const e2 = arrow(ox, oy, d2[0], d2[1], v2.mag * scale, '#7dd3fc', 3, v2.label);
            // parallelogram (dashed)
            ctx.strokeStyle = faint; ctx.lineWidth = 1.4; ctx.setLineDash([5, 5]);
            ctx.beginPath(); ctx.moveTo(e1[0], e1[1]); ctx.lineTo(e1[0] + (e2[0] - ox), e1[1] + (e2[1] - oy)); ctx.lineTo(e2[0], e2[1]); ctx.stroke(); ctx.setLineDash([]);

            if (p && p.dir) {
                const d = DIR[p.dir] || DIR.right; const col = p.correct ? acc : bad;
                arrow(ox, oy, d[0], d[1], R * scale, col, 4, `R = ${(+R.toFixed(2))} ${unit}`);
                ctx.fillStyle = col; ctx.font = '700 15px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(p.correct ? `✓ R = √(${v1.mag}² + ${v2.mag}²) = ${(+R.toFixed(2))} ${unit}, pointing outward` : '✗ check the direction — the resultant leaves the origin between the two vectors', VW / 2, 34);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [v1, v2, unit, R]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '780 / 560' }}>
                    <span className="cw-badge">Resultant vector</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Which arrow is the resultant?'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>Combine the two vectors tip-to-tail (or by the parallelogram). The resultant leaves the origin, pointing <em>outward</em> between them, with magnitude √(a² + b²).</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
