import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: force_vectors ─────────────────────────────────────────────────────
// Free-body diagram: an object on the ground with labelled force arrows radiating
// from its centre, plus a motion arrow. The MCQ Options probe highlights the
// chosen force and shows the work it does on the kinetic-energy store — positive
// (force along motion), negative (against), or zero (perpendicular). Reusable for
// any "which force does work / what is the resultant" free-body question.
// config: {
//   motion:'right'|'left'|'none', object:'pan'|'box',
//   forces:[{dir:'up'|'down'|'left'|'right', label}],
//   optionsLead, hint,
//   options:[{label, dir, correct, note}]
// }

const VW = 1000, VH = 520;
const DIRV = { up: [0, -1], down: [0, 1], left: [-1, 0], right: [1, 0] };

function arrow(ctx, x, y, dx, dy, len, col, lw) {
    const ex = x + dx * len, ey = y + dy * len, a = Math.atan2(dy, dx), h = 16;
    ctx.strokeStyle = col; ctx.lineWidth = lw; ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(ex, ey); ctx.stroke();
    ctx.fillStyle = col; ctx.beginPath(); ctx.moveTo(ex, ey);
    ctx.lineTo(ex - h * Math.cos(a - 0.4), ey - h * Math.sin(a - 0.4));
    ctx.lineTo(ex - h * Math.cos(a + 0.4), ey - h * Math.sin(a + 0.4));
    ctx.closePath(); ctx.fill();
    return [ex, ey];
}

export default function ForceVectors({ config = {}, onReady, onAddToNote }) {
    const { forces = [], motion = 'right' } = config;
    const [picked, setPicked] = useState(null);
    const stRef = useRef(picked); stRef.current = picked;
    const cvRef = useRef(null);

    useEffect(() => { onReady && onReady({ getState: () => ({ picked: picked?.label }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    const workSign = (dir) => {
        const m = DIRV[motion]; if (!m) return 'zero';
        const d = DIRV[dir]; const dot = d[0] * m[0] + d[1] * m[1];
        return dot > 0 ? 'positive' : dot < 0 ? 'negative' : 'zero';
    };

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0, t0 = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const draw = (ts) => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            if (!t0) t0 = ts; const frame = (ts - t0) / 1000;
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399'), bad = '#fb7185', warn = '#fbbf24';
            const p = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            const groundY = VH * 0.62;
            // ground
            ctx.strokeStyle = faint; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(60, groundY); ctx.lineTo(VW - 60, groundY); ctx.stroke();
            for (let hx = 80; hx < VW - 60; hx += 30) { ctx.beginPath(); ctx.moveTo(hx, groundY); ctx.lineTo(hx - 14, groundY + 16); ctx.stroke(); }

            // object (a shallow pan / box), gently bobbing to the right when moving
            const bob = motion !== 'none' ? Math.sin(frame * 2) * 6 : 0;
            const cx = VW / 2 + bob, cy = groundY - 34;
            ctx.fillStyle = 'rgba(255,255,255,.06)'; ctx.strokeStyle = ink; ctx.lineWidth = 2.6;
            ctx.beginPath();
            ctx.moveTo(cx - 90, cy - 16); ctx.lineTo(cx - 70, cy + 16); ctx.lineTo(cx + 70, cy + 16); ctx.lineTo(cx + 90, cy - 16);
            ctx.stroke();
            ctx.beginPath(); ctx.ellipse(cx, cy - 16, 90, 9, 0, 0, 6.283); ctx.stroke();

            // motion arrow + label
            if (motion !== 'none') {
                const md = DIRV[motion];
                arrow(ctx, cx + md[0] * 120, cy - 70, md[0], md[1], 60, warn, 3);
                ctx.fillStyle = warn; ctx.font = 'italic 600 15px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('accelerates ' + motion, cx + md[0] * 150, cy - 84);
            }

            // force arrows
            const L = 120;
            forces.forEach((f) => {
                const d = DIRV[f.dir]; if (!d) return;
                const sel = p && p.dir === f.dir;
                const col = sel ? (p.correct ? acc : bad) : ink;
                const [ex, ey] = arrow(ctx, cx, cy - 16, d[0], d[1], L, col, sel ? 5 : 3);
                ctx.fillStyle = col; ctx.font = (sel ? '700 ' : '600 ') + '16px Inter, sans-serif';
                ctx.textAlign = d[0] < 0 ? 'right' : d[0] > 0 ? 'left' : 'center';
                ctx.textBaseline = d[1] < 0 ? 'bottom' : d[1] > 0 ? 'top' : 'middle';
                ctx.fillText(f.label, ex + d[0] * 14, ey + d[1] * 14);
            });

            // work badge for the selected force
            if (p && p.dir) {
                const ws = workSign(p.dir);
                const wc = ws === 'positive' ? acc : ws === 'negative' ? bad : faint;
                const txt = ws === 'positive' ? 'W = F·d  (positive → increases KE)' : ws === 'negative' ? 'W = −F·d  (negative → removes KE)' : 'W = 0  (perpendicular → no work on KE)';
                ctx.fillStyle = 'rgba(0,0,0,.35)'; ctx.fillRect(VW / 2 - 250, 30, 500, 42);
                ctx.strokeStyle = wc; ctx.lineWidth = 2; ctx.strokeRect(VW / 2 - 250, 30, 500, 42);
                ctx.fillStyle = wc; ctx.font = '700 18px "JetBrains Mono", monospace'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                ctx.fillText(txt, VW / 2, 51);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [forces, motion]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '25 / 13' }}>
                    <span className="cw-badge">Free-body diagram</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Which force does the work?'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>Work that changes the kinetic energy store is done by a force <em>along the direction of motion</em>.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this diagram to my notes</button>)}
            </div>
        </div>
    );
}
