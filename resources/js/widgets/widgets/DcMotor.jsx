import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: dc_motor ──────────────────────────────────────────────────────────
// A current-carrying coil spins between magnet poles. Sliders for the magnetic
// field strength, the current, and the number of turns each speed the coil up, so
// a student can see which factors change the speed of rotation. The MCQ Options
// probe gives feedback on which listed factors are correct.
// config: { optionsLead, hint, options:[{label, correct, note}] }

const VW = 900, VH = 460;

export default function DcMotor({ config = {}, onReady, onAddToNote }) {
    const [B, setB] = useState(3), [I, setI] = useState(3), [Turns, setTurns] = useState(3);
    const [picked, setPicked] = useState(null);
    const stRef = useRef({ B, I, Turns, picked }); stRef.current = { B, I, Turns, picked };
    const cvRef = useRef(null);
    const angRef = useRef(0);

    useEffect(() => { onReady && onReady({ getState: () => ({ B, I, Turns }), setState: (s) => { if (s?.B) setB(s.B); if (s?.I) setI(s.I); if (s?.Turns) setTurns(s.Turns); } }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0, last = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const draw = (ts) => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            if (!last) last = ts; const dt = (ts - last) / 1000; last = ts;
            const { B: b, I: i, Turns: n } = stRef.current;
            const speed = 0.25 * b * i * n; // rad/s ∝ B·I·N
            angRef.current += dt * speed;
            const ang = angRef.current;
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399'), warn = '#fbbf24';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            const cx = VW / 2, cy = VH / 2 - 20, R = 150, H = 200;
            // magnet poles
            ctx.fillStyle = 'rgba(251,113,133,.5)'; ctx.fillRect(60, cy - H / 2, 90, H);
            ctx.fillStyle = 'rgba(96,165,250,.5)'; ctx.fillRect(VW - 150, cy - H / 2, 90, H);
            ctx.fillStyle = '#0a1622'; ctx.font = '700 34px Georgia, serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText('N', 105, cy); ctx.fillText('S', VW - 105, cy);
            // field lines N→S
            ctx.strokeStyle = faint; ctx.lineWidth = 1.5; ctx.fillStyle = faint;
            for (let k = -2; k <= 2; k++) { const y = cy + k * 55; ctx.beginPath(); ctx.moveTo(155, y); ctx.lineTo(VW - 155, y); ctx.stroke(); ctx.beginPath(); ctx.moveTo(VW - 158, y); ctx.lineTo(VW - 170, y - 5); ctx.lineTo(VW - 170, y + 5); ctx.closePath(); ctx.fill(); }

            // coil rotating about the vertical axis: horizontal half-width = R·cos(ang)
            const hw = R * Math.cos(ang); const front = Math.cos(ang) >= 0;
            ctx.strokeStyle = warn; ctx.lineWidth = 3 + n * 0.6;
            ctx.beginPath(); ctx.rect(cx - hw, cy - H / 2, hw * 2, H); ctx.stroke();
            // the two current-carrying sides + force arrows (the couple that spins it)
            const fLen = 20 + b * i * 4;
            const sideForce = (sx, up) => { ctx.strokeStyle = acc; ctx.lineWidth = 3; const y0 = cy, y1 = cy - (up ? fLen : -fLen); ctx.beginPath(); ctx.moveTo(sx, y0); ctx.lineTo(sx, y1); ctx.stroke(); ctx.fillStyle = acc; ctx.beginPath(); ctx.moveTo(sx, y1); ctx.lineTo(sx - 6, y1 + (up ? 12 : -12)); ctx.lineTo(sx + 6, y1 + (up ? 12 : -12)); ctx.closePath(); ctx.fill(); };
            if (Math.abs(hw) > 12) { sideForce(cx - hw, front); sideForce(cx + hw, !front); }
            // axle + split-ring commutator + brushes
            ctx.strokeStyle = ink; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(cx, cy + H / 2); ctx.lineTo(cx, cy + H / 2 + 40); ctx.stroke();
            ctx.strokeStyle = warn; ctx.lineWidth = 3; ctx.beginPath(); ctx.arc(cx - 10, cy + H / 2 + 52, 12, -1.2, 1.2); ctx.stroke(); ctx.beginPath(); ctx.arc(cx + 10, cy + H / 2 + 52, 12, 1.9, 4.4); ctx.stroke();
            ctx.fillStyle = faint; ctx.font = '600 12px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'alphabetic'; ctx.fillText('commutator', cx, cy + H / 2 + 82);

            // rotation-speed readout
            ctx.fillStyle = ink; ctx.font = '700 16px "JetBrains Mono", monospace'; ctx.textAlign = 'left'; ctx.fillText(`speed ∝ B × I × N = ${b} × ${i} × ${n} = ${b * i * n}`, 40, 40);
            const p = stRef.current.picked;
            if (p) { ctx.fillStyle = p.correct ? acc : '#fb7185'; ctx.font = '700 15px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(p.correct ? '✓ correct' : '✗ not this combination', VW / 2, VH - 12); }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, []);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '900 / 460' }}>
                    <span className="cw-badge">d.c. motor</span>
                    <canvas ref={cvRef} />
                </div>
                <div className="cw-slider-row">
                    <label className="cw-slider-lab">field B = {B}<input type="range" min="1" max="5" step="1" value={B} onChange={(e) => setB(+e.target.value)} /></label>
                    <label className="cw-slider-lab">current I = {I}<input type="range" min="1" max="5" step="1" value={I} onChange={(e) => setI(+e.target.value)} /></label>
                    <label className="cw-slider-lab">turns N = {Turns}<input type="range" min="1" max="5" step="1" value={Turns} onChange={(e) => setTurns(+e.target.value)} /></label>
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Which factors affect the speed?'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>Turn up the field, the current, or the number of turns — each one makes the coil spin faster.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
