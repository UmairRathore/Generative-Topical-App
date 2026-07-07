import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: inclined_plane ────────────────────────────────────────────────────
// A block resting on a slope, with the candidate force arrows drawn from the
// block's centre exactly as in the exam figure (vertical weight, up-slope,
// down-slope, and the normal perpendicular to the surface). Clicking an option
// highlights that arrow on the real slope diagram and explains it — so the student
// interacts with the actual scenario, not a flat free-body abstraction.
// config: {
//   slopeAngle (deg), optionsLead, hint,
//   options:[{label, dir:'down'|'up'|'up-slope'|'down-slope'|'perp-out'|'perp-in', correct, note}]
// }

const VW = 900, VH = 540;

export default function InclinedPlane({ config = {}, onReady, onAddToNote }) {
    const { slopeAngle = 22 } = config;
    const [picked, setPicked] = useState(null);
    const stRef = useRef(picked); stRef.current = picked;
    const cvRef = useRef(null);
    const th = slopeAngle * Math.PI / 180;
    // canvas directions (y down). Slope descends left→right.
    const DIR = {
        down: [0, 1], up: [0, -1],
        'up-slope': [-Math.cos(th), -Math.sin(th)], 'down-slope': [Math.cos(th), Math.sin(th)],
        'perp-out': [Math.sin(th), -Math.cos(th)], 'perp-in': [-Math.sin(th), Math.cos(th)],
    };

    useEffect(() => { onReady && onReady({ getState: () => ({ picked: picked?.label }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const draw = () => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399'), bad = '#fb7185';
            const p = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            // slope: a bar from upper-left to lower-right
            const x0 = 90, y0 = VH * 0.42, x1 = VW - 90, y1 = y0 + (x1 - x0) * Math.tan(th);
            ctx.strokeStyle = '#9aa6b8'; ctx.lineWidth = 3;
            ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke();
            // ground hatching under slope
            ctx.fillStyle = 'rgba(160,180,205,.1)'; ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.lineTo(x1, VH - 20); ctx.lineTo(x0, VH - 20); ctx.closePath(); ctx.fill();

            // block sitting on the slope midpoint, aligned to the slope
            const bx = (x0 + x1) / 2, by = (y0 + y1) / 2;
            ctx.save(); ctx.translate(bx, by); ctx.rotate(th);
            ctx.fillStyle = 'rgba(200,210,225,.18)'; ctx.strokeStyle = ink; ctx.lineWidth = 2.4;
            ctx.beginPath(); ctx.rect(-52, -74, 104, 74); ctx.fill(); ctx.stroke();
            ctx.restore();
            const centre = [bx, by - 40]; // approx block centre above the surface

            const arrow = (dir, len, col, lw, label) => {
                const d = DIR[dir] || DIR.down; const ex = centre[0] + d[0] * len, ey = centre[1] + d[1] * len;
                ctx.strokeStyle = col; ctx.lineWidth = lw; ctx.beginPath(); ctx.moveTo(centre[0], centre[1]); ctx.lineTo(ex, ey); ctx.stroke();
                const a = Math.atan2(d[1], d[0]); ctx.fillStyle = col; ctx.beginPath(); ctx.moveTo(ex, ey); ctx.lineTo(ex - 15 * Math.cos(a - 0.4), ey - 15 * Math.sin(a - 0.4)); ctx.lineTo(ex - 15 * Math.cos(a + 0.4), ey - 15 * Math.sin(a + 0.4)); ctx.closePath(); ctx.fill();
                if (label) { ctx.fillStyle = col; ctx.font = '700 16px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(label, ex + d[0] * 16, ey + d[1] * 16 + 5); }
            };
            // all candidate arrows faint, labelled by their option letter
            (config.options || []).forEach((o) => arrow(o.dir, 110, p && p.label === o.label ? (o.correct ? acc : bad) : 'rgba(166,196,179,.4)', p && p.label === o.label ? 5 : 2, o.label));
            ctx.fillStyle = ink; ctx.beginPath(); ctx.arc(centre[0], centre[1], 4, 0, 6.283); ctx.fill();

            if (p) { const col = p.correct ? acc : bad; ctx.fillStyle = col; ctx.font = '700 15px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(p.correct ? '✓ weight always acts vertically downward — toward the Earth' : '✗ that is not the gravitational force', VW / 2, 34); }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [slopeAngle, config.options]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '9 / 5.4' }}>
                    <span className="cw-badge">Block on a slope</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Which arrow shows the force?'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>Gravity (weight) always pulls <strong>vertically downward</strong>, toward the centre of the Earth — no matter how the surface is tilted.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
