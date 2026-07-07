import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: gas_cylinder ──────────────────────────────────────────────────────
// Air trapped in a cylinder by a free piston (atmospheric pressure outside).
// Particles bounce around; a "Heat the gas" toggle speeds them up and pushes the
// piston out so the pressure stays constant. The MCQ Options probe reports whether
// each quantity goes up / down / stays the same as the gas is heated.
// config: { optionsLead, hint, options:[{label, trend:'up'|'down'|'same', correct, note}] }

const VW = 900, VH = 440;
const N = 34;

export default function GasCylinder({ config = {}, onReady, onAddToNote }) {
    const [picked, setPicked] = useState(null);
    const [hot, setHot] = useState(false);
    const stRef = useRef({ picked, hot }); stRef.current = { picked, hot };
    const cvRef = useRef(null);
    const partsRef = useRef(null);
    const pistonRef = useRef(0.62); // piston x as fraction of cylinder inner width

    useEffect(() => { onReady && onReady({ getState: () => ({ hot }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const cylL = 90, cylTop = 90, cylBot = VH - 70, cylRightMax = VW - 120;
        if (!partsRef.current) {
            partsRef.current = Array.from({ length: N }, (_, i) => ({
                x: cylL + 30 + (i * 53) % 380, y: cylTop + 20 + (i * 37) % (cylBot - cylTop - 40),
                vx: (i % 2 ? 1 : -1) * (1.4 + (i % 5) * 0.2), vy: (i % 3 ? 1 : -1) * (1.2 + (i % 4) * 0.2),
            }));
        }

        const draw = () => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399');
            const { hot: isHot } = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            // piston eases toward its target position (further out when hot)
            const targetFrac = isHot ? 0.82 : 0.6;
            pistonRef.current += (targetFrac - pistonRef.current) * 0.05;
            const pistonX = cylL + pistonRef.current * (cylRightMax - cylL);

            // speed target
            const speedMul = isHot ? 1.9 : 1;
            const ps = partsRef.current;
            ps.forEach((p) => {
                const sp = Math.hypot(p.vx, p.vy) || 1; const want = (isHot ? 2.6 : 1.5);
                p.vx *= 1 + (want / sp - 1) * 0.04; p.vy *= 1 + (want / sp - 1) * 0.04;
                p.x += p.vx; p.y += p.vy;
                if (p.x < cylL + 8) { p.x = cylL + 8; p.vx = Math.abs(p.vx); }
                if (p.x > pistonX - 10) { p.x = pistonX - 10; p.vx = -Math.abs(p.vx); }
                if (p.y < cylTop + 8) { p.y = cylTop + 8; p.vy = Math.abs(p.vy); }
                if (p.y > cylBot - 8) { p.y = cylBot - 8; p.vy = -Math.abs(p.vy); }
            });

            // cylinder walls
            ctx.strokeStyle = ink; ctx.lineWidth = 3;
            ctx.beginPath(); ctx.moveTo(cylL, cylTop); ctx.lineTo(cylL, cylBot); ctx.moveTo(cylL, cylTop); ctx.lineTo(cylRightMax + 60, cylTop); ctx.moveTo(cylL, cylBot); ctx.lineTo(cylRightMax + 60, cylBot); ctx.stroke();
            // heat glow under cylinder when hot
            if (isHot) { ctx.fillStyle = 'rgba(251,146,60,.22)'; ctx.fillRect(cylL, cylBot + 4, pistonX - cylL, 14); ctx.fillStyle = '#fb923c'; ctx.font = '600 14px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText('heat', (cylL + pistonX) / 2, cylBot + 34); }

            // particles
            ps.forEach((p) => { ctx.fillStyle = isHot ? '#fb923c' : ink; ctx.beginPath(); ctx.arc(p.x, p.y, 5, 0, 6.283); ctx.fill(); });

            // piston
            ctx.fillStyle = 'rgba(160,180,200,.4)'; ctx.fillRect(pistonX, cylTop - 4, 16, cylBot - cylTop + 8);
            ctx.strokeStyle = '#c0cad8'; ctx.lineWidth = 2; ctx.strokeRect(pistonX, cylTop - 4, 16, cylBot - cylTop + 8);
            ctx.beginPath(); ctx.moveTo(pistonX + 16, (cylTop + cylBot) / 2); ctx.lineTo(pistonX + 70, (cylTop + cylBot) / 2); ctx.stroke();
            ctx.fillStyle = faint; ctx.font = '600 13px Inter, sans-serif'; ctx.textAlign = 'left'; ctx.fillText('piston', pistonX + 20, cylTop - 12);
            ctx.textAlign = 'right'; ctx.fillText('atmospheric pressure →', VW - 20, cylTop - 12);

            // picked quantity trend badge
            const p = stRef.current.picked;
            if (p) {
                const col = p.correct ? acc : '#fb7185';
                const sym = p.trend === 'up' ? '▲ increases' : p.trend === 'down' ? '▼ decreases' : '＝ stays the same';
                ctx.fillStyle = 'rgba(0,0,0,.4)'; ctx.fillRect(VW / 2 - 210, 20, 420, 38);
                ctx.strokeStyle = col; ctx.lineWidth = 2; ctx.strokeRect(VW / 2 - 210, 20, 420, 38);
                ctx.fillStyle = col; ctx.font = '700 16px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                ctx.fillText(sym + (p.correct ? '  — this is the one that decreases' : ''), VW / 2, 39); ctx.textBaseline = 'alphabetic';
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, []);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '9 / 4.4' }}>
                    <span className="cw-badge">Gas in a cylinder</span>
                    <canvas ref={cvRef} />
                </div>
                <div className="cw-btn-row">
                    <button className="cw-btn" onClick={() => setHot((v) => !v)}>{hot ? '❄ Let it cool' : '🔥 Heat the gas'}</button>
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Which quantity decreases as the gas is heated?'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>Heating speeds up the particles, so the piston moves out and the volume grows. Watch which quantity falls.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
