import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: transformer ───────────────────────────────────────────────────────
// An a.c. supply drives the primary coil; the alternating current makes a CHANGING
// magnetic flux in the iron core, which induces an alternating e.m.f. in the
// secondary coil (lighting the lamp). The animation shows the a.c., the pulsing
// flux in the core, and the induced secondary current. The MCQ Options probe gives
// feedback. Turns ratio can be shown (Np/Ns).
// config: { primaryTurns, secondaryTurns, optionsLead, hint, options:[{label, correct, note}] }

const VW = 900, VH = 420;

export default function Transformer({ config = {}, onReady, onAddToNote }) {
    const { primaryTurns = 4, secondaryTurns = 8 } = config;
    const [picked, setPicked] = useState(null);
    const stRef = useRef(picked); stRef.current = picked;
    const cvRef = useRef(null);

    useEffect(() => { onReady && onReady({ getState: () => ({ picked: picked?.label }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0, t0 = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const draw = (ts) => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            if (!t0) t0 = ts; const t = (ts - t0) / 1000;
            const ac = Math.sin(t * 3); // the alternating drive
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399');
            const p = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            // iron core (square laminated ring)
            const coX = 300, coY = 70, coW = 300, coH = 260, thick = 46;
            ctx.strokeStyle = '#9aa6b8'; ctx.lineWidth = thick; ctx.strokeRect(coX, coY, coW, coH);
            ctx.strokeStyle = 'rgba(120,140,160,.5)'; ctx.lineWidth = 1; for (let x = coX - 20; x < coX + coW + 20; x += 8) { ctx.beginPath(); ctx.moveTo(x, coY - 24); ctx.lineTo(x, coY + 24); ctx.stroke(); }
            ctx.fillStyle = faint; ctx.font = '600 13px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText('iron core', VW / 2, coY + coH + 40);

            // changing flux glow in the core (pulses with |ac|)
            ctx.strokeStyle = `rgba(52,211,153,${0.25 + 0.5 * Math.abs(ac)})`; ctx.lineWidth = 6; ctx.strokeRect(coX, coY, coW, coH);
            // flux direction arrows around the core (reverse with ac sign)
            const fdir = ac >= 0 ? 1 : -1; ctx.fillStyle = acc;
            const fa = (x, y, dx, dy) => { ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x - dx * 10 + dy * 6, y - dy * 10 - dx * 6); ctx.lineTo(x - dx * 10 - dy * 6, y - dy * 10 + dx * 6); ctx.closePath(); ctx.fill(); };
            fa(coX + coW / 2 + fdir * 20, coY, fdir, 0); fa(coX + coW / 2 - fdir * 20, coY + coH, -fdir, 0);

            // coils (drawn as stacks of loops on the left & right legs)
            const coil = (cxx, turns, col, label) => {
                ctx.strokeStyle = col; ctx.lineWidth = 3;
                const top = coY + 20, gap = 20;
                for (let k = 0; k < turns; k++) { const y = top + k * gap; ctx.beginPath(); ctx.ellipse(cxx, y, 26, 9, 0, 0, 6.283); ctx.stroke(); }
                ctx.fillStyle = col; ctx.font = '600 13px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(label, cxx, top - 16);
            };
            coil(coX, primaryTurns, '#7dd3fc', `primary (${primaryTurns} turns)`);
            coil(coX + coW, secondaryTurns, '#fbbf24', `secondary (${secondaryTurns} turns)`);

            // a.c. supply on the left, current dots pulsing with ac
            ctx.strokeStyle = ink; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(120, coY + 40); ctx.lineTo(coX - 30, coY + 40); ctx.moveTo(120, coY + coH - 40); ctx.lineTo(coX - 30, coY + coH - 40); ctx.stroke();
            ctx.beginPath(); ctx.arc(105, coY + coH / 2, 26, 0, 6.283); ctx.stroke(); ctx.fillStyle = ink; ctx.font = 'italic 700 18px Georgia, serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText('∿', 105, coY + coH / 2); ctx.textBaseline = 'alphabetic'; ctx.fillText('a.c.', 105, coY + coH / 2 + 44);

            // lamp on the right, brightness follows |ac| (induced current)
            const lampX = VW - 70, lampY = coY + coH / 2;
            ctx.strokeStyle = ink; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(coX + coW + 30, coY + 40); ctx.lineTo(lampX, coY + 40); ctx.lineTo(lampX, lampY - 24); ctx.moveTo(coX + coW + 30, coY + coH - 40); ctx.lineTo(lampX, coY + coH - 40); ctx.lineTo(lampX, lampY + 24); ctx.stroke();
            ctx.fillStyle = `rgba(251,191,36,${0.2 + 0.7 * Math.abs(ac)})`; ctx.beginPath(); ctx.arc(lampX, lampY, 22, 0, 6.283); ctx.fill();
            ctx.strokeStyle = '#fbbf24'; ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(lampX, lampY, 22, 0, 6.283); ctx.stroke(); ctx.beginPath(); ctx.moveTo(lampX - 15, lampY - 15); ctx.lineTo(lampX + 15, lampY + 15); ctx.moveTo(lampX + 15, lampY - 15); ctx.lineTo(lampX - 15, lampY + 15); ctx.stroke();

            // caption
            ctx.fillStyle = faint; ctx.font = '600 14px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'alphabetic';
            ctx.fillText('a.c. → changing flux in the core → induced a.c. in the secondary', VW / 2, 32);

            if (p) { ctx.fillStyle = p.correct ? acc : '#fb7185'; ctx.font = '700 15px Inter, sans-serif'; ctx.fillText(p.correct ? '✓ correct' : '✗ not this one', VW / 2, VH - 8); }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [primaryTurns, secondaryTurns]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '900 / 420' }}>
                    <span className="cw-badge">Transformer</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Which statement is correct?'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>A transformer only works on <strong>a.c.</strong> — the alternating current makes a <em>changing</em> magnetic field in the iron core, which induces a voltage in the secondary.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
