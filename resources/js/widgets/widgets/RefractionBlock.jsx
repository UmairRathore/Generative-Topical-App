import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: refraction_block ──────────────────────────────────────────────────
// Light refracting into a glass block (Snell's law). Draws the block, the incident
// ray from the air, the normal, and the refracted ray bending TOWARD the normal —
// all live: drag the angle of incidence and the refracted ray + angles update via
// sin i = n sin r, with animated light pulses travelling along the ray. The MCQ
// options are candidate refraction angles; clicking one draws that ray so the
// student sees which matches. A real interactive diagram, not a calculator.
// config: {
//   incidence (deg from normal), n, surfaceAngleLabel?,
//   optionsLead, hint, options:[{label, r (deg), correct, note}]
// }

const VW = 900, VH = 560;
const D2R = Math.PI / 180, R2D = 180 / Math.PI;

export default function RefractionBlock({ config = {}, onReady, onAddToNote }) {
    const { n = 1.5 } = config;
    const [inc, setInc] = useState(config.incidence ?? 75);
    const [picked, setPicked] = useState(null);
    const stRef = useRef({ inc, picked }); stRef.current = { inc, picked };
    const cvRef = useRef(null);

    const refr = Math.asin(Math.min(1, Math.sin(inc * D2R) / n)) * R2D; // true refraction angle

    useEffect(() => { onReady && onReady({ getState: () => ({ inc }), setState: (s) => setInc(s?.inc ?? 75) }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0, t0 = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const draw = (ts) => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            if (!t0) t0 = ts; const frame = (ts - t0) / 1000;
            const { inc: i, picked: p } = stRef.current;
            const rTrue = Math.asin(Math.min(1, Math.sin(i * D2R) / n)) * R2D;
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399'), bad = '#fb7185', warn = '#fbbf24';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bgc = ctx.createLinearGradient(0, 0, 0, VH); bgc.addColorStop(0, '#0a1622'); bgc.addColorStop(1, '#0c1a12'); ctx.fillStyle = bgc; ctx.fillRect(0, 0, VW, VH);

            const cx = VW * 0.44, surfaceY = VH * 0.42, L = 260;
            // glass block
            ctx.fillStyle = 'rgba(160,180,205,.14)'; ctx.fillRect(120, surfaceY, VW - 240, VH - surfaceY - 40);
            ctx.strokeStyle = '#9aa6b8'; ctx.lineWidth = 2.5; ctx.strokeRect(120, surfaceY, VW - 240, VH - surfaceY - 40);
            ctx.fillStyle = faint; ctx.font = '600 16px Inter, sans-serif'; ctx.textAlign = 'left'; ctx.fillText('glass block  (n = ' + n + ')', VW - 320, surfaceY + 44);
            ctx.textAlign = 'right'; ctx.fillText('air', 200, surfaceY - 14);

            // normal (dashed, both sides)
            ctx.strokeStyle = faint; ctx.setLineDash([7, 5]); ctx.lineWidth = 1.6;
            ctx.beginPath(); ctx.moveTo(cx, surfaceY - 170); ctx.lineTo(cx, surfaceY + 200); ctx.stroke(); ctx.setLineDash([]);
            ctx.fillStyle = faint; ctx.font = 'italic 600 14px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText('normal', cx + 44, surfaceY - 150);

            // incident ray (air side): comes from upper-left at angle i to the normal
            const ix = cx - L * Math.sin(i * D2R), iy = surfaceY - L * Math.cos(i * D2R);
            ctx.strokeStyle = warn; ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(ix, iy); ctx.lineTo(cx, surfaceY); ctx.stroke();
            // arrowhead toward surface
            { const a = Math.atan2(surfaceY - iy, cx - ix); ctx.fillStyle = warn; const hx = ix + (cx - ix) * 0.5, hy = iy + (surfaceY - iy) * 0.5; ctx.beginPath(); ctx.moveTo(hx + 12 * Math.cos(a), hy + 12 * Math.sin(a)); ctx.lineTo(hx + 12 * Math.cos(a) - 13 * Math.cos(a - 0.4), hy + 12 * Math.sin(a) - 13 * Math.sin(a - 0.4)); ctx.lineTo(hx + 12 * Math.cos(a) - 13 * Math.cos(a + 0.4), hy + 12 * Math.sin(a) - 13 * Math.sin(a + 0.4)); ctx.closePath(); ctx.fill(); }

            // true refracted ray (into glass), bends toward normal: angle rTrue from downward normal
            const rx = cx + L * Math.sin(rTrue * D2R), ry = surfaceY + L * Math.cos(rTrue * D2R);
            ctx.strokeStyle = 'rgba(52,211,153,.5)'; ctx.lineWidth = 2; ctx.setLineDash([4, 4]); ctx.beginPath(); ctx.moveTo(cx, surfaceY); ctx.lineTo(rx, ry); ctx.stroke(); ctx.setLineDash([]);

            // picked candidate refracted ray
            if (p) {
                const pr = p.r, px = cx + L * Math.sin(pr * D2R), py = surfaceY + L * Math.cos(pr * D2R);
                const col = p.correct ? acc : bad;
                ctx.strokeStyle = col; ctx.lineWidth = 3.5; ctx.beginPath(); ctx.moveTo(cx, surfaceY); ctx.lineTo(px, py); ctx.stroke();
                const a = Math.atan2(py - surfaceY, px - cx); ctx.fillStyle = col; ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(px - 15 * Math.cos(a - 0.4), py - 15 * Math.sin(a - 0.4)); ctx.lineTo(px - 15 * Math.cos(a + 0.4), py - 15 * Math.sin(a + 0.4)); ctx.closePath(); ctx.fill();
                ctx.fillStyle = col; ctx.font = '700 15px "JetBrains Mono", monospace'; ctx.textAlign = 'left'; ctx.fillText('r = ' + p.r + '°', px + 10, py);
            }

            // animated light pulses travelling in then out along the TRUE path
            for (let k = 0; k < 5; k++) {
                const t = (frame * 0.4 + k / 5) % 1;
                let x, y;
                if (t < 0.5) { const s = t / 0.5; x = ix + (cx - ix) * s; y = iy + (surfaceY - iy) * s; }
                else { const s = (t - 0.5) / 0.5; x = cx + (rx - cx) * s; y = surfaceY + (ry - surfaceY) * s; }
                ctx.fillStyle = warn; ctx.globalAlpha = 0.9; ctx.beginPath(); ctx.arc(x, y, 4, 0, 6.283); ctx.fill(); ctx.globalAlpha = 1;
            }

            // angle arcs
            const arc = (a0, a1, rad, col, label) => { ctx.strokeStyle = col; ctx.lineWidth = 2; ctx.beginPath(); const steps = 24; for (let s = 0; s <= steps; s++) { const a = a0 + (a1 - a0) * s / steps; ctx[s ? 'lineTo' : 'moveTo'](cx + rad * Math.sin(a * D2R), surfaceY - rad * Math.cos(a * D2R)); } ctx.stroke(); ctx.fillStyle = col; ctx.font = '600 13px "JetBrains Mono", monospace'; ctx.textAlign = 'center'; ctx.fillText(label, cx + (rad + 18) * Math.sin((a0 + a1) / 2 * D2R), surfaceY - (rad + 18) * Math.cos((a0 + a1) / 2 * D2R)); };
            arc(-i, 0, 44, warn, 'i = ' + Math.round(i) + '°');
            arc(180, 180 + rTrue, 44, acc, 'r = ' + rTrue.toFixed(0) + '°');
            // pivot
            ctx.fillStyle = ink; ctx.beginPath(); ctx.arc(cx, surfaceY, 4, 0, 6.283); ctx.fill();
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [n]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '9 / 5.6' }}>
                    <span className="cw-badge">Refraction (Snell's law)</span>
                    <canvas ref={cvRef} />
                </div>
                <div className="cw-slider-row">
                    <label className="cw-slider-lab">angle of incidence i = {Math.round(inc)}°<input type="range" min="0" max="89" step="1" value={inc} onChange={(e) => setInc(+e.target.value)} /></label>
                    <span style={{ color: 'var(--ok)', fontFamily: 'monospace', fontSize: 13 }}>→ r = {refr.toFixed(1)}°</span>
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'What is the angle of refraction r?'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>Drag the angle of incidence and watch the ray bend. Snell's law: sin i = n sin r, so r = sin⁻¹(sin i ÷ n). Light bends <em>toward</em> the normal entering glass.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
