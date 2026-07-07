import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: fleming_lhr ───────────────────────────────────────────────────────
// Fleming's left-hand rule. Two magnet poles set the field (N→S), a charged beam
// enters, and the MCQ Options probe shows the resulting force — an in-plane arrow
// (up/down) or a ⊗ / ⊙ symbol (into / out of the page). Handles the charge sign
// (a negative beam means conventional current is opposite to its velocity).
// config: {
//   left:'N'|'S', right:'N'|'S',           // poles → field points from N to S
//   beam:{dir:'down'|'up'|'left'|'right', label, charge:'negative'|'positive'},
//   optionsLead, hint,
//   options:[{label, force:'up'|'down'|'into'|'out', correct, note}]
// }

const VW = 1000, VH = 520;

export default function FlemingLHR({ config = {}, onReady, onAddToNote }) {
    const { left = 'N', right = 'S', beam = {} } = config;
    const [picked, setPicked] = useState(null);
    const stRef = useRef(picked); stRef.current = picked;
    const cvRef = useRef(null);

    useEffect(() => { onReady && onReady({ getState: () => ({ picked: picked?.label }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0, t0 = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        // field points from the N pole toward the S pole
        const fieldDir = left === 'N' ? 'right' : 'left';

        const draw = (ts) => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            if (!t0) t0 = ts; const frame = (ts - t0) / 1000;
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399'), bad = '#fb7185';
            const p = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            const cx = VW / 2, cy = VH / 2, gap = 300;
            // magnet poles
            const pole = (x, letter, colFill) => {
                ctx.fillStyle = colFill; ctx.strokeStyle = ink; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.rect(x - 70, cy - 100, 140, 200); ctx.fill(); ctx.stroke();
                ctx.fillStyle = '#0a1622'; ctx.font = '700 46px Georgia, serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                ctx.fillText(letter, x, cy);
            };
            pole(cx - gap, left, left === 'N' ? 'rgba(251,113,133,.55)' : 'rgba(96,165,250,.55)');
            pole(cx + gap, right, right === 'N' ? 'rgba(251,113,133,.55)' : 'rgba(96,165,250,.55)');

            // field lines N→S across the gap
            ctx.strokeStyle = faint; ctx.lineWidth = 1.8; ctx.fillStyle = faint;
            for (let k = -2; k <= 2; k++) {
                const y = cy + k * 42; const xa = cx - gap + 72, xb = cx + gap - 72;
                const dir = fieldDir === 'right' ? 1 : -1;
                ctx.beginPath(); ctx.moveTo(xa, y); ctx.lineTo(xb, y); ctx.stroke();
                const hx = dir > 0 ? xb - 4 : xa + 4;
                ctx.beginPath(); ctx.moveTo(hx, y); ctx.lineTo(hx - dir * 14, y - 6); ctx.lineTo(hx - dir * 14, y + 6); ctx.closePath(); ctx.fill();
            }
            ctx.font = '600 14px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillStyle = faint; ctx.textBaseline = 'alphabetic';
            ctx.fillText('magnetic field (N → S)', cx, cy - 116);

            // beam of particles entering (animated dots along its direction)
            const bd = beam.dir || 'down';
            const bv = { up: [0, -1], down: [0, 1], left: [-1, 0], right: [1, 0] }[bd];
            const bx0 = cx - bv[0] * 210, by0 = cy - bv[1] * 210; // start offset back along -velocity
            ctx.strokeStyle = '#fbbf24'; ctx.lineWidth = 3;
            ctx.beginPath(); ctx.moveTo(bx0, by0); ctx.lineTo(cx, cy); ctx.stroke();
            // arrowhead at centre
            const ba = Math.atan2(bv[1], bv[0]);
            ctx.fillStyle = '#fbbf24'; ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(cx - 16 * Math.cos(ba - 0.4), cy - 16 * Math.sin(ba - 0.4)); ctx.lineTo(cx - 16 * Math.cos(ba + 0.4), cy - 16 * Math.sin(ba + 0.4)); ctx.closePath(); ctx.fill();
            // moving particles
            for (let k = 0; k < 4; k++) {
                const t = (frame * 0.6 + k / 4) % 1; const px = bx0 + (cx - bx0) * t, py = by0 + (cy - by0) * t;
                ctx.fillStyle = '#fbbf24'; ctx.beginPath(); ctx.arc(px, py, 5, 0, 6.283); ctx.fill();
            }
            ctx.fillStyle = '#fbbf24'; ctx.font = '600 15px Inter, sans-serif'; ctx.textAlign = bv[0] ? 'center' : 'left';
            ctx.fillText(beam.label || 'beam', bx0 + (bv[0] ? 0 : 16), by0 - 12);

            // force result on pick
            if (p && p.force) {
                const col = p.correct ? acc : bad;
                if (p.force === 'into' || p.force === 'out') {
                    ctx.strokeStyle = col; ctx.lineWidth = 3; ctx.beginPath(); ctx.arc(cx, cy, 30, 0, 6.283); ctx.stroke();
                    if (p.force === 'into') { // ⊗
                        ctx.beginPath(); ctx.moveTo(cx - 21, cy - 21); ctx.lineTo(cx + 21, cy + 21); ctx.moveTo(cx + 21, cy - 21); ctx.lineTo(cx - 21, cy + 21); ctx.stroke();
                    } else { // ⊙
                        ctx.fillStyle = col; ctx.beginPath(); ctx.arc(cx, cy, 7, 0, 6.283); ctx.fill();
                    }
                    ctx.fillStyle = col; ctx.font = '700 16px Inter, sans-serif'; ctx.textAlign = 'center';
                    ctx.fillText(p.force === 'into' ? 'force: into the page' : 'force: out of the page', cx, cy + 66);
                } else {
                    const fv = { up: [0, -1], down: [0, 1] }[p.force];
                    ctx.strokeStyle = col; ctx.lineWidth = 5; ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(cx + fv[0] * 110, cy + fv[1] * 110); ctx.stroke();
                    const fa = Math.atan2(fv[1], fv[0]); const ex = cx + fv[0] * 110, ey = cy + fv[1] * 110;
                    ctx.fillStyle = col; ctx.beginPath(); ctx.moveTo(ex, ey); ctx.lineTo(ex - 18 * Math.cos(fa - 0.4), ey - 18 * Math.sin(fa - 0.4)); ctx.lineTo(ex - 18 * Math.cos(fa + 0.4), ey - 18 * Math.sin(fa + 0.4)); ctx.closePath(); ctx.fill();
                    ctx.font = '700 16px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText('force: ' + p.force, cx, ey + fv[1] * 22 + 6);
                }
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [left, right, beam]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '25 / 13' }}>
                    <span className="cw-badge">Fleming's left-hand rule</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'What is the direction of the force?'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>Left hand: <strong>F</strong>irst finger = <strong>F</strong>ield, se<strong>C</strong>ond finger = <strong>C</strong>urrent, thu<strong>M</strong>b = <strong>M</strong>otion (force). A negative beam means the conventional current points opposite to the beam.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
