import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: states_of_matter ──────────────────────────────────────────────────
// Solid ⇄ liquid ⇄ gas with the four changes of state as labelled arrows. Each
// state box animates its particle arrangement (packed & vibrating / loose / free
// & fast). Each MCQ option names the four arrows in order; on pick the widget
// writes those names onto the arrows, green where the name matches the true change
// of state and red where it doesn't.
// config: {
//   arrows:[{id, from:'solid'|'liquid'|'gas', to:..., truth}],   // in W,X,Y,Z order
//   optionsLead, hint,
//   options:[{label, names:[...4], correct, note}]
// }

const VW = 940, VH = 440;
const STATE_X = { gas: 150, liquid: VW / 2, solid: VW - 150 };

export default function StatesOfMatter({ config = {}, onReady, onAddToNote }) {
    const arrows = config.arrows || [];
    const [picked, setPicked] = useState(null);
    const stRef = useRef(picked); stRef.current = picked;
    const cvRef = useRef(null);
    const partsRef = useRef(null);

    useEffect(() => { onReady && onReady({ getState: () => ({ picked: picked?.label }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const boxW = 150, boxH = 110, boxY = 60;
        if (!partsRef.current) {
            const mk = (n) => Array.from({ length: n }, (_, i) => ({ i, ox: (i % 4) * 30 + 16, oy: Math.floor(i / 4) * 30 + 16, vx: (i % 2 ? 1 : -1) * 1.4, vy: (i % 3 ? 1 : -1) * 1.2, ph: i }));
            partsRef.current = { solid: mk(12), liquid: mk(11), gas: mk(9) };
        }

        const drawState = (label, cx, t) => {
            const bx = cx - boxW / 2;
            ctx.strokeStyle = cssVar('--ink-soft', '#A6C4B3'); ctx.lineWidth = 2.4; ctx.strokeRect(bx, boxY, boxW, boxH);
            ctx.fillStyle = cssVar('--ink-faint', '#68877A'); ctx.font = '700 16px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(label, cx, boxY - 12);
            const ps = partsRef.current[label];
            ps.forEach((p) => {
                let x, y;
                if (label === 'solid') { x = bx + 20 + p.ox + Math.sin(t * 4 + p.ph) * 2.2; y = boxY + 14 + p.oy + Math.cos(t * 4 + p.ph) * 2.2; }
                else if (label === 'liquid') { p.ox += p.vx * 0.5; p.oy += p.vy * 0.5; if (p.ox < 8 || p.ox > boxW - 16) p.vx *= -1; if (p.oy < 8 || p.oy > boxH - 16) p.vy *= -1; x = bx + p.ox; y = boxY + p.oy; }
                else { p.ox += p.vx * 1.4; p.oy += p.vy * 1.4; if (p.ox < 8 || p.ox > boxW - 16) p.vx *= -1; if (p.oy < 8 || p.oy > boxH - 16) p.vy *= -1; x = bx + p.ox; y = boxY + p.oy; }
                ctx.fillStyle = cssVar('--ink-soft', '#A6C4B3'); ctx.beginPath(); ctx.arc(x, y, 5, 0, 6.283); ctx.fill();
            });
        };

        const draw = (ts) => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            const t = ts / 1000;
            const faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399'), bad = '#fb7185', ink = cssVar('--ink-soft', '#A6C4B3');
            const p = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            drawState('gas', STATE_X.gas, t); drawState('liquid', STATE_X.liquid, t); drawState('solid', STATE_X.solid, t);

            // arrows between adjacent states
            arrows.forEach((ar, idx) => {
                const x1 = STATE_X[ar.from], x2 = STATE_X[ar.to];
                const leftPair = (ar.from === 'gas' || ar.to === 'gas');
                const gapL = Math.min(x1, x2) + boxW / 2 + 8, gapR = Math.max(x1, x2) - boxW / 2 - 8;
                // stack two arrows per gap: the one pointing left sits on top row, pointing right on bottom
                const pointsLeft = x2 < x1;
                const y = boxY + (pointsLeft ? 34 : boxH - 34);
                const ax1 = pointsLeft ? gapR : gapL, ax2 = pointsLeft ? gapL : gapR;
                let col = faint;
                if (p && p.names) { col = p.names[idx] === ar.truth ? acc : bad; }
                ctx.strokeStyle = col; ctx.lineWidth = 2.4; ctx.beginPath(); ctx.moveTo(ax1, y); ctx.lineTo(ax2, y); ctx.stroke();
                const dir = ax2 > ax1 ? 1 : -1; ctx.fillStyle = col; ctx.beginPath(); ctx.moveTo(ax2, y); ctx.lineTo(ax2 - dir * 12, y - 6); ctx.lineTo(ax2 - dir * 12, y + 6); ctx.closePath(); ctx.fill();
                // label: id, plus the picked name
                ctx.fillStyle = p && p.names ? col : ink; ctx.font = '700 14px Inter, sans-serif'; ctx.textAlign = 'center';
                const my = (gapL + gapR) / 2;
                ctx.fillText(ar.id + (p && p.names ? ': ' + p.names[idx] : ''), my, y - 12);
            });

            if (p) { ctx.fillStyle = p.correct ? acc : bad; ctx.font = '700 15px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(p.correct ? '✓ every arrow named correctly' : '✗ at least one change of state is mis-named', VW / 2, VH - 24); }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [arrows]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '94 / 44' }}>
                    <span className="cw-badge">Changes of state</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Name the four changes of state (W, X, Y, Z)'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>Follow each arrow's direction: e.g. liquid → gas is <em>boiling</em>, gas → liquid is <em>condensation</em>.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
