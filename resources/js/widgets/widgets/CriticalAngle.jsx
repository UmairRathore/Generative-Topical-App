import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: critical_angle ────────────────────────────────────────────────────
// Light in a denser medium (glass/water) meeting the boundary with a less-dense
// medium (air). A fixed incident ray strikes at angle c from the normal. Each MCQ
// option is a possible outcome — refract into air at an angle, pass straight
// through, refract + reflect, or (at the critical angle) refract ALONG the boundary
// with partial reflection. On pick the widget draws that option's rays and judges.
// config: {
//   belowLabel, aboveLabel, incidenceDeg,   // c, from the normal
//   optionsLead, hint,
//   options:[{label, refract:'along'|'none'|<degAboveHorizIntoAir>, reflect:bool, straight:bool, correct, note}]
// }

const VW = 900, VH = 560;
const D2R = Math.PI / 180;
const dirPt = (px, py, deg, r) => [px + r * Math.cos(deg * D2R), py - r * Math.sin(deg * D2R)];

export default function CriticalAngle({ config = {}, onReady, onAddToNote }) {
    const { belowLabel = 'glass', aboveLabel = 'air', incidenceDeg = 42 } = config;
    const [picked, setPicked] = useState(null);
    const stRef = useRef(picked); stRef.current = picked;
    const cvRef = useRef(null);

    useEffect(() => { onReady && onReady({ getState: () => ({ picked: picked?.label }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const ray = (px, py, deg, r, col, lw, toPivot, label) => {
            const [ex, ey] = dirPt(px, py, deg, r); ctx.strokeStyle = col; ctx.lineWidth = lw;
            ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(ex, ey); ctx.stroke();
            const hd = (toPivot ? deg + 180 : deg) * D2R; const [hx, hy] = dirPt(px, py, deg, r * 0.55);
            ctx.fillStyle = col; ctx.beginPath(); ctx.moveTo(hx, hy); ctx.lineTo(hx - 15 * Math.cos(hd - 0.4), hy + 15 * Math.sin(hd - 0.4)); ctx.lineTo(hx - 15 * Math.cos(hd + 0.4), hy + 15 * Math.sin(hd + 0.4)); ctx.closePath(); ctx.fill();
            if (label) { const [lx, ly] = dirPt(px, py, deg, r + 22); ctx.fillStyle = col; ctx.font = '600 13px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(label, lx, ly); }
        };

        const draw = () => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399'), bad = '#fb7185', warn = '#fbbf24';
            const p = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            const px = VW / 2, py = VH * 0.46, R = 210;
            // denser medium below (shaded), less-dense above
            ctx.fillStyle = 'rgba(120,150,180,.16)'; ctx.fillRect(0, py, VW, VH - py);
            ctx.strokeStyle = '#9aa6b8'; ctx.lineWidth = 2.5; ctx.beginPath(); ctx.moveTo(40, py); ctx.lineTo(VW - 40, py); ctx.stroke();
            ctx.fillStyle = faint; ctx.font = '600 16px Inter, sans-serif'; ctx.textAlign = 'left';
            ctx.fillText(aboveLabel, 50, py - 14); ctx.fillText(belowLabel, 50, py + 26);

            // normal (dashed both ways)
            ctx.strokeStyle = faint; ctx.setLineDash([7, 5]); ctx.lineWidth = 1.6;
            ctx.beginPath(); ctx.moveTo(px, py - R); ctx.lineTo(px, py + R); ctx.stroke(); ctx.setLineDash([]);
            ctx.fillStyle = faint; ctx.font = 'italic 600 13px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText('normal', px, py - R - 8);

            // incident ray fixed (from down-left, angle c from the downward normal)
            const incDeg = 270 - incidenceDeg; // down-left
            ray(px, py, incDeg, R, warn, 3, true, 'incident ray');
            // angle c arc
            ctx.strokeStyle = warn; ctx.lineWidth = 2.5; ctx.beginPath();
            for (let i = 0; i <= 24; i++) { const t = 270 - incidenceDeg * i / 24; const [ax, ay] = dirPt(px, py, t, 50); ctx[i ? 'lineTo' : 'moveTo'](ax, ay); } ctx.stroke();
            const [cx0, cy0] = dirPt(px, py, 270 - incidenceDeg / 2, 70); ctx.fillStyle = warn; ctx.font = 'italic 700 18px Georgia, serif'; ctx.textAlign = 'center'; ctx.fillText('c', cx0, cy0);

            if (p) {
                const col = p.correct ? acc : bad;
                // reflected ray back into the denser medium (down-right)
                if (p.reflect) ray(px, py, 270 + incidenceDeg, R, col, 2.6, false, 'reflected');
                // refracted / transmitted ray
                if (p.straight) {
                    ray(px, py, (incDeg + 180) % 360, R, col, 2.6, false, 'goes straight on');
                } else if (p.refract === 'along') {
                    ray(px, py, 0, R, col, 3.2, false, 'refracted along surface (90°)');
                    ray(px, py, 180, 0, col, 0, false, null);
                } else if (typeof p.refract === 'number') {
                    ray(px, py, p.refract, R, col, 2.6, false, 'refracted into air');
                }
                ctx.fillStyle = col; ctx.font = '700 15px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(p.correct ? '✓ at the critical angle the ray refracts along the boundary' : '✗ not what happens exactly at the critical angle', px, 32);
            }
            ctx.fillStyle = ink; ctx.beginPath(); ctx.arc(px, py, 4, 0, 6.283); ctx.fill();
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [belowLabel, aboveLabel, incidenceDeg]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '9 / 6' }}>
                    <span className="cw-badge">Critical angle</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'What happens to the light?'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>At exactly the <strong>critical angle</strong>, the refracted ray bends to 90° and travels <em>along the boundary</em>. Beyond it, the light is totally internally reflected.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
