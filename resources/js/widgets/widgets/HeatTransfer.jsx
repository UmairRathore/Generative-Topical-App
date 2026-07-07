import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: heat_transfer ─────────────────────────────────────────────────────
// Two thermal-energy transfer animations selected by `mode`:
//   'conduction' — a solid (metal pan) on a hotplate; energy passes particle-to-
//                  particle, shown as a heat colour front rising through the metal.
//   'convection' — a fluid heated at its base circulates: warm fluid rises, cools
//                  and sinks, a looping convection current.
// The MCQ Options probe gives feedback on the chosen answer.
// config: { mode, optionsLead, hint, options:[{label, correct, note}] }

const VW = 900, VH = 480;

export default function HeatTransfer({ config = {}, onReady, onAddToNote }) {
    const { mode = 'conduction' } = config;
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
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399');
            const p = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            if (mode === 'conduction') {
                const panL = 230, panR = VW - 230, panTop = 150, panBot = 300;
                // hotplate (glowing element) under the pan
                const glow = 0.6 + 0.4 * Math.sin(t * 3);
                ctx.fillStyle = `rgba(251,80,40,${0.35 + 0.25 * glow})`; ctx.fillRect(panL - 20, panBot + 8, panR - panL + 40, 34);
                ctx.strokeStyle = '#fb5028'; ctx.lineWidth = 2; ctx.strokeRect(panL - 20, panBot + 8, panR - panL + 40, 34);
                ctx.fillStyle = faint; ctx.font = '600 14px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText('hotplate', VW / 2, panBot + 66);
                // pan: heat colour front rising from the base
                for (let y = panBot; y > panTop; y -= 6) {
                    const depth = (panBot - y) / (panBot - panTop); // 0 at base, 1 at top
                    const front = (t * 0.35) % 1.4; // rising heat front
                    const heat = Math.max(0, 1 - Math.abs(depth - front * 0.7) * 2) * (1 - depth * 0.3);
                    const r = Math.round(120 + heat * 135), gg = Math.round(120 - heat * 40), b = Math.round(130 - heat * 80);
                    ctx.fillStyle = `rgb(${r},${gg},${b})`; ctx.fillRect(panL, y - 6, panR - panL, 6);
                }
                ctx.strokeStyle = ink; ctx.lineWidth = 2.5; ctx.strokeRect(panL, panTop, panR - panL, panBot - panTop);
                // handle
                ctx.beginPath(); ctx.moveTo(panR, panTop + 30); ctx.lineTo(panR + 90, panTop + 20); ctx.stroke();
                // vibrating particles (more vigorous near the hot base)
                for (let i = 0; i < 40; i++) {
                    const px = panL + 24 + (i * 53) % (panR - panL - 48);
                    const row = Math.floor(((i * 53) / (panR - panL - 48))) ;
                    const py = panBot - 24 - (i % 5) * 26;
                    const amp = 3 + 5 * ((panBot - py) / (panBot - panTop));
                    ctx.fillStyle = ink; ctx.beginPath(); ctx.arc(px + Math.sin(t * 6 + i) * amp, py + Math.cos(t * 6 + i) * amp, 3.5, 0, 6.283); ctx.fill();
                }
                ctx.fillStyle = faint; ctx.font = '600 14px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText('metal pan', VW / 2, panTop - 14);
                // upward energy arrow
                ctx.strokeStyle = '#fb923c'; ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(VW / 2, panBot - 6); ctx.lineTo(VW / 2, panTop + 20); ctx.stroke();
                ctx.fillStyle = '#fb923c'; ctx.beginPath(); ctx.moveTo(VW / 2, panTop + 12); ctx.lineTo(VW / 2 - 8, panTop + 28); ctx.lineTo(VW / 2 + 8, panTop + 28); ctx.closePath(); ctx.fill();
            } else {
                // convection: beaker of water heated at the base
                const bx = 300, bw = 300, bTop = 90, bBot = VH - 90;
                ctx.fillStyle = 'rgba(62,167,224,.12)'; ctx.fillRect(bx, bTop, bw, bBot - bTop);
                ctx.strokeStyle = ink; ctx.lineWidth = 2.5; ctx.beginPath(); ctx.moveTo(bx, bTop); ctx.lineTo(bx, bBot); ctx.lineTo(bx + bw, bBot); ctx.lineTo(bx + bw, bTop); ctx.stroke();
                // flame at base
                const glow = 0.6 + 0.4 * Math.sin(t * 5);
                ctx.fillStyle = `rgba(251,146,60,${0.5 * glow})`; for (let i = 0; i < 5; i++) { const fx = bx + bw * (i + 0.5) / 5; ctx.beginPath(); ctx.moveTo(fx - 14, bBot + 30); ctx.quadraticCurveTo(fx, bBot - 10, fx + 14, bBot + 30); ctx.fill(); }
                ctx.fillStyle = faint; ctx.font = '600 14px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText('heat', VW / 2, bBot + 52);
                // convection loop: particles rise in the middle (warm), sink at the sides (cool)
                const cx = bx + bw / 2, midW = 60;
                for (let i = 0; i < 16; i++) {
                    const ph = (t * 0.25 + i / 16) % 1; // 0..1 around the loop
                    let x, y, warm;
                    if (ph < 0.4) { const s = ph / 0.4; x = cx; y = bBot - 20 - s * (bBot - bTop - 40); warm = true; } // rise centre
                    else if (ph < 0.5) { const s = (ph - 0.4) / 0.1; x = cx + s * (bw / 2 - 30); y = bTop + 20; warm = false; } // across top
                    else if (ph < 0.9) { const s = (ph - 0.5) / 0.4; x = cx + bw / 2 - 30; y = bTop + 20 + s * (bBot - bTop - 40); warm = false; } // sink side
                    else { const s = (ph - 0.9) / 0.1; x = cx + (bw / 2 - 30) * (1 - s); y = bBot - 20; warm = true; } // across base
                    ctx.fillStyle = warm ? '#fb7185' : '#60a5fa'; ctx.beginPath(); ctx.arc(x, y, 6, 0, 6.283); ctx.fill();
                }
                // loop arrows
                ctx.strokeStyle = faint; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.moveTo(cx, bBot - 40); ctx.lineTo(cx, bTop + 40); ctx.stroke();
                ctx.fillStyle = '#fb7185'; ctx.beginPath(); ctx.moveTo(cx, bTop + 34); ctx.lineTo(cx - 7, bTop + 50); ctx.lineTo(cx + 7, bTop + 50); ctx.closePath(); ctx.fill();
                ctx.fillStyle = faint; ctx.textAlign = 'left'; ctx.fillText('warm water rises', cx + 14, (bTop + bBot) / 2);
            }

            if (p) { const col = p.correct ? acc : '#fb7185'; ctx.fillStyle = col; ctx.font = '700 16px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(p.correct ? '✓ correct' : '✗ not this one', VW / 2, 40); }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [mode]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '15 / 8' }}>
                    <span className="cw-badge">{mode === 'conduction' ? 'Conduction' : 'Convection'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Pick the correct answer'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || (mode === 'conduction'
                    ? <>In a solid, energy passes from particle to particle by <strong>conduction</strong> — metals do this well.</>
                    : <>Heated fluid <strong>expands</strong>, becomes <em>less dense</em>, and <strong>rises</strong>; cooler fluid sinks — a convection current.</>)}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
