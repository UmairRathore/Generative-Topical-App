import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: liquid_pressure ───────────────────────────────────────────────────
// Four beakers of the SAME liquid, each with a marked point at a different depth
// below the surface. Liquid pressure depends only on depth (p = ρgh), not on the
// amount of liquid or the beaker shape. Clicking an option highlights that beaker,
// draws the depth arrow from the surface to the point, and bars the relative
// pressure — the deepest point wins. Reusable for any liquid-pressure question.
// config: {
//   options:[{label, fill (0..1 liquid height/beaker), point (0..1 depth of X from beaker top), correct, note, wide?}]
//   optionsLead, hint
// }

const VW = 1000, VH = 460;

export default function LiquidPressure({ config = {}, onReady, onAddToNote }) {
    const beakers = config.options || [];
    const [picked, setPicked] = useState(null);
    const stRef = useRef(picked); stRef.current = picked;
    const cvRef = useRef(null);

    useEffect(() => { onReady && onReady({ getState: () => ({ picked: picked?.label }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    // depth of the marked point below the liquid surface, as a 0..1 fraction of beaker height
    const depthOf = (b) => Math.max(0, b.point - (1 - b.fill));
    const dmax = Math.max(...beakers.map(depthOf), 0.001);

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const draw = () => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399'), water = '#3EA7E0';
            const p = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            const n = beakers.length, slot = VW / n, bw = Math.min(150, slot * 0.62), bh = VH * 0.66, topY = VH * 0.14;
            beakers.forEach((b, i) => {
                const cx = slot * (i + 0.5), left = cx - bw / 2, right = cx + bw / 2;
                const sel = p && p.label === b.label;
                const surfaceY = topY + (1 - b.fill) * bh, pointY = topY + b.point * bh, botY = topY + bh;
                // beaker walls
                ctx.strokeStyle = sel ? (b.correct ? acc : '#fb7185') : ink; ctx.lineWidth = sel ? 3 : 2;
                ctx.beginPath(); ctx.moveTo(left, topY); ctx.lineTo(left, botY); ctx.lineTo(right, botY); ctx.lineTo(right, topY); ctx.stroke();
                // liquid
                ctx.fillStyle = 'rgba(62,167,224,.20)'; ctx.fillRect(left + 2, surfaceY, bw - 4, botY - surfaceY - 1);
                ctx.strokeStyle = water; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(left + 2, surfaceY); ctx.lineTo(right - 2, surfaceY); ctx.stroke();
                // marked point X
                const xcol = sel ? (b.correct ? acc : '#fb7185') : ink;
                ctx.strokeStyle = xcol; ctx.lineWidth = 3;
                ctx.beginPath(); ctx.moveTo(cx - 8, pointY - 8); ctx.lineTo(cx + 8, pointY + 8); ctx.moveTo(cx + 8, pointY - 8); ctx.lineTo(cx - 8, pointY + 8); ctx.stroke();
                ctx.fillStyle = xcol; ctx.font = '700 16px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'bottom'; ctx.fillText(b.label, cx - 16, pointY - 4);
                ctx.textBaseline = 'alphabetic';
                // when selected: depth arrow + pressure
                if (sel) {
                    ctx.strokeStyle = xcol; ctx.lineWidth = 2; ctx.setLineDash([5, 4]);
                    ctx.beginPath(); ctx.moveTo(right + 10, surfaceY); ctx.lineTo(right + 10, pointY); ctx.stroke(); ctx.setLineDash([]);
                    ctx.fillStyle = xcol; ctx.font = '600 13px "JetBrains Mono", monospace'; ctx.textAlign = 'left';
                    ctx.fillText('depth h', right + 16, (surfaceY + pointY) / 2);
                }
            });

            // pressure bars along the bottom for every beaker (relative p ∝ depth)
            const barTop = VH - 26;
            beakers.forEach((b, i) => {
                const cx = slot * (i + 0.5), d = depthOf(b), frac = d / dmax;
                const isMax = Math.abs(d - dmax) < 1e-6;
                ctx.fillStyle = isMax ? acc : 'rgba(166,196,179,.35)';
                const bw2 = slot * 0.5;
                ctx.fillRect(cx - bw2 / 2, barTop - frac * 26, bw2, frac * 26);
            });
            ctx.fillStyle = faint; ctx.font = '600 13px Inter, sans-serif'; ctx.textAlign = 'left'; ctx.textBaseline = 'alphabetic';
            ctx.fillText('relative pressure  (p ∝ depth below surface)', 20, VH - 4);
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [beakers, dmax]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '25 / 11' }}>
                    <span className="cw-badge">Liquid pressure = ρgh</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {beakers.length > 0 && (
                    <Options options={beakers} lead={config.optionsLead || 'Where is the pressure greatest?'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>Liquid pressure depends only on the <em>depth below the surface</em> — not on how much liquid there is or the beaker's shape.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
