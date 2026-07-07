import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: circular_motion ──────────────────────────────────────────────────
// Uniform circular motion: an object on a circular track (roller-coaster loop).
// The resultant (centripetal) force ALWAYS points to the centre — shown as a live
// green arrow as the truck orbits. Clicking A/B/C/D draws that candidate direction
// (tangent / outward / down / centre) to compare. Matches the exam figure.
// config.options[i].dir = 'centre' | 'outward' | 'tangent' | 'down'

const VW = 1000, VH = 620;

function dirVec(type, ox, oy, tx, ty, sign) {
    let rx = tx - ox, ry = ty - oy; const r = Math.hypot(rx, ry) || 1; rx /= r; ry /= r;
    switch (type) {
        case 'centre': return [-rx, -ry];
        case 'outward': return [rx, ry];
        case 'down': return [0, 1];
        case 'tangent': return [sign * -ry, sign * rx];
        default: return [0, 0];
    }
}
function arrow(ctx, x, y, dx, dy, len, color, w = 5) {
    const ex = x + dx * len, ey = y + dy * len, a = Math.atan2(dy, dx), head = 12 + w;
    ctx.strokeStyle = color; ctx.fillStyle = color; ctx.lineWidth = w; ctx.lineCap = 'round';
    ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(ex, ey); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(ex, ey); ctx.lineTo(ex - head * Math.cos(a - 0.4), ey - head * Math.sin(a - 0.4)); ctx.lineTo(ex - head * Math.cos(a + 0.4), ey - head * Math.sin(a + 0.4)); ctx.closePath(); ctx.fill();
}

export default function CircularMotion({ config = {}, onReady, onAddToNote }) {
    const [picked, setPicked] = useState(null);
    const [playing, setPlaying] = useState(false);   // start paused at the figure position
    const stRef = useRef({ picked, playing }); stRef.current = { picked, playing };
    const cvRef = useRef(null);
    const angRef = useRef(-1.0);   // start top-right, like the figure

    useEffect(() => { onReady && onReady({ getState: () => ({ ...config }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0, last = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();
        const frame = (time) => {
            if (!last) last = time; const dt = time - last; last = time;
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(frame); return; }
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399');
            const ox = VW / 2, oy = VH / 2 - 25, R = 215, sign = -1;
            if (stRef.current.playing) angRef.current += sign * dt * 0.0006;
            const th = angRef.current, tx = ox + R * Math.cos(th), ty = oy + R * Math.sin(th);

            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            // ground
            const gy = oy + R + 44; ctx.strokeStyle = '#5a6675'; ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(ox - R - 90, gy); ctx.lineTo(ox + R + 90, gy); ctx.stroke();
            for (let i = -2; i <= 2; i++) { const sxp = ox + i * 90; ctx.beginPath(); ctx.moveTo(sxp, gy); ctx.lineTo(sxp - 14, gy + 22); ctx.stroke(); }

            // track (double line)
            ctx.strokeStyle = faint; ctx.lineWidth = 3; ctx.beginPath(); ctx.arc(ox, oy, R, 0, 6.283); ctx.stroke();
            ctx.lineWidth = 1.5; ctx.beginPath(); ctx.arc(ox, oy, R - 11, 0, 6.283); ctx.stroke();
            // centre
            ctx.fillStyle = faint; ctx.beginPath(); ctx.arc(ox, oy, 5, 0, 6.283); ctx.fill();
            ctx.font = '600 15px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText('centre', ox, oy + 24);

            // truck
            ctx.fillStyle = '#9aa6b8'; ctx.strokeStyle = '#c3ccd8'; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.arc(tx, ty, 17, 0, 6.283); ctx.fill(); ctx.stroke();
            ctx.fillStyle = ink; ctx.fillText('truck', tx + (tx > ox ? 46 : -46), ty - 26);

            // live centripetal (resultant) arrow — always to the centre
            const cd = dirVec('centre', ox, oy, tx, ty, sign);
            arrow(ctx, tx, ty, cd[0], cd[1], 92, acc, 5);
            ctx.fillStyle = acc; ctx.font = '600 13px Inter, sans-serif'; ctx.fillText('resultant', tx + cd[0] * 116, ty + cd[1] * 116);

            // picked candidate
            const p = stRef.current.picked;
            if (p) {
                const d = dirVec(p.dir, ox, oy, tx, ty, sign), col = p.correct ? acc : '#fb7185';
                arrow(ctx, tx, ty, d[0], d[1], 120, col, 4);
                ctx.fillStyle = col; ctx.font = '700 22px "JetBrains Mono", monospace'; ctx.fillText(p.label, tx + d[0] * 142, ty + d[1] * 142);
            }
            raf = requestAnimationFrame(frame);
        };
        raf = requestAnimationFrame(frame);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, []);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 11' }}>
                    <span className="cw-badge">Live · the resultant force</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (playing ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setPlaying((p) => !p)}>{playing ? '⏸ pause' : '▶ play'}</button>
                </div>
                {config.options?.length > 0 && (
                    <Options options={config.options} lead="Which arrow is the resultant force?" onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">
                    The <b style={{ color: '#34D399' }}>green arrow</b> is the resultant force. In circular motion at constant speed it <b>always points to the centre</b> (centripetal). Click each answer to compare its direction.
                </Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this diagram to my notes</button>)}
            </div>
        </div>
    );
}
