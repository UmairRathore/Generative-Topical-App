import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: potential_divider ─────────────────────────────────────────────────
// Two resistors in series share the supply p.d. in the ratio of their resistances
// (the same current flows through both, so V = IR ⇒ V1/V2 = R1/R2). Sliders change
// R1 and R2; the canvas shows the live p.d.s and the two ratios side by side. Each
// MCQ option is an expression; on pick it is evaluated with the current resistances
// and compared with V1/V2, showing which expression always matches.
// config: { supply, r1, r2, optionsLead, hint, options:[{label, expr, correct, note}] }

const VW = 900, VH = 320;
const exprVal = (expr, r1, r2) => {
    switch (expr) {
        case 'R1/R2': return r1 / r2;
        case 'R2/R1': return r2 / r1;
        case 'R1^2/R2^2': return (r1 / r2) ** 2;
        case 'R2^2/R1^2': return (r2 / r1) ** 2;
        default: return NaN;
    }
};
const pretty = { 'R1/R2': 'R₁ / R₂', 'R2/R1': 'R₂ / R₁', 'R1^2/R2^2': 'R₁² / R₂²', 'R2^2/R1^2': 'R₂² / R₁²' };

export default function PotentialDivider({ config = {}, onReady, onAddToNote }) {
    const { supply = 6 } = config;
    const [r1, setR1] = useState(config.r1 ?? 4);
    const [r2, setR2] = useState(config.r2 ?? 2);
    const [picked, setPicked] = useState(null);
    const stRef = useRef({ r1, r2 }); stRef.current = { r1, r2 };
    const cvRef = useRef(null);

    const I = supply / (r1 + r2), V1 = I * r1, V2 = I * r2;

    useEffect(() => { onReady && onReady({ getState: () => ({ r1, r2 }), setState: (s) => { if (s?.r1) setR1(s.r1); if (s?.r2) setR2(s.r2); } }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0, t0 = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const draw = (ts) => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            if (!t0) t0 = ts; const t = (ts - t0) / 1000;
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399');
            const { r1: R1, r2: R2 } = stRef.current; const II = supply / (R1 + R2), v1 = II * R1, v2 = II * R2;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            const y = 120, x0 = 80, x1 = VW - 80;
            ctx.strokeStyle = ink; ctx.lineWidth = 2.4; ctx.beginPath(); ctx.moveTo(x0, y); ctx.lineTo(x1, y); ctx.stroke();
            // two resistor boxes
            const box = (cx, label, ohms, vv, tone) => {
                const w = 150, h = 44;
                ctx.fillStyle = 'rgba(255,255,255,.05)'; ctx.strokeStyle = tone; ctx.lineWidth = 2.4; ctx.beginPath(); ctx.rect(cx - w / 2, y - h / 2, w, h); ctx.fill(); ctx.stroke();
                ctx.fillStyle = tone; ctx.font = '700 18px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(`${label} = ${ohms} Ω`, cx, y);
                // p.d. arrow under
                ctx.strokeStyle = tone; ctx.lineWidth = 1.8; ctx.beginPath(); ctx.moveTo(cx - w / 2, y + 44); ctx.lineTo(cx + w / 2, y + 44); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(cx + w / 2, y + 44); ctx.lineTo(cx + w / 2 - 10, y + 39); ctx.lineTo(cx + w / 2 - 10, y + 49); ctx.closePath(); ctx.fill();
                ctx.font = '700 15px "JetBrains Mono", monospace'; ctx.textBaseline = 'alphabetic'; ctx.fillText(`${label === 'R₁' ? 'V₁' : 'V₂'} = ${vv.toFixed(2)} V`, cx, y + 70);
            };
            box(VW * 0.32, 'R₁', R1, v1, '#7dd3fc');
            box(VW * 0.68, 'R₂', R2, v2, '#fbbf24');
            // supply + moving current dots
            ctx.fillStyle = faint; ctx.font = '600 14px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'alphabetic'; ctx.fillText(`${supply} V supply`, VW / 2, 40);
            for (let k = 0; k < 8; k++) { const px = x0 + ((t * 120 + k * (x1 - x0) / 8) % (x1 - x0)); ctx.fillStyle = acc; ctx.globalAlpha = .8; ctx.beginPath(); ctx.arc(px, y, 3.2, 0, 6.283); ctx.fill(); ctx.globalAlpha = 1; }
            // ratio comparison
            ctx.fillStyle = ink; ctx.font = '700 17px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            ctx.fillText(`V₁ / V₂ = ${(v1 / v2).toFixed(2)}      R₁ / R₂ = ${(R1 / R2).toFixed(2)}`, VW / 2, VH - 26);
            ctx.fillStyle = acc; ctx.font = '600 13px Inter, sans-serif'; ctx.fillText('they are always equal — same current through both', VW / 2, VH - 8);
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [supply]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '900 / 320' }}>
                    <span className="cw-badge">Potential divider</span>
                    <canvas ref={cvRef} />
                </div>
                <div className="cw-slider-row">
                    <label className="cw-slider-lab">R₁ = {r1} Ω<input type="range" min="1" max="10" step="1" value={r1} onChange={(e) => setR1(+e.target.value)} /></label>
                    <label className="cw-slider-lab">R₂ = {r2} Ω<input type="range" min="1" max="10" step="1" value={r2} onChange={(e) => setR2(+e.target.value)} /></label>
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options
                        options={config.options.map((o) => ({ ...o, note: (o.note || '') + (o.expr ? `  (now ${pretty[o.expr] || o.expr} = ${exprVal(o.expr, r1, r2).toFixed(2)}, V₁/V₂ = ${(V1 / V2).toFixed(2)})` : '') }))}
                        lead={config.optionsLead || 'Which expression equals V₁ / V₂?'}
                        onPick={(o) => setPicked(o)}
                    />
                )}
                <Flag kind="neutral">{config.hint || <>The same current flows through both resistors, so V = IR gives V₁/V₂ = R₁/R₂. Drag the sliders — the two ratios stay equal.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
