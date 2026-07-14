import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag, Button } from '../primitives.jsx';

// ── Widget: conduction_lab ───────────────────────────────────────────────────
// Bespoke Thermal Conduction hero simulator (5054 · 2.3.1). Two rods heated at
// one end: a METAL (copper) and a NON-METAL (glass). Both carry the classic
// good/bad-conductor experiment - wax-stuck pins fall in sequence as the heat
// front reaches them, racing down the metal and crawling down the glass. And
// both show the MECHANISM: a vibrating lattice row (in every solid) plus, in
// the metal only, free (delocalised) electrons relaying energy fast from hot
// end to cold. Diffusivities are illustrative; the CONTRAST is the physics.
//
// config: { paused } · Notes contract: getState/setState carry {t, paused}.

const RODS = [
    { key: 'copper', label: 'copper (metal)', col: '#d97706', k: 1.0, electrons: true },
    { key: 'glass', label: 'glass (non-metal)', col: '#8fb4d9', k: 0.14, electrons: false },
];
const N_PINS = 4;

export default function ConductionLab({ config = {}, onReady, onAddToNote }) {
    const [running, setRunning] = useState(!config.paused);
    const cvRef = useRef(null);
    const simRef = useRef({ t: 0 });
    const st = useRef({}); st.current = { running };

    const reset = () => { simRef.current.t = 0; };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, t: simRef.current.t, paused: !st.current.running }),
            setState: (s) => {
                if (typeof s?.t === 'number') simRef.current.t = Math.max(0, s.t);
                if (typeof s?.paused === 'boolean') setRunning(!s.paused);
            },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, last = performance.now();
        const draw = (now) => {
            const dt = Math.min(0.05, (now - last) / 1000); last = now;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return;
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
            const cyan = '#38BDF8', amber = '#FBBF24', rose = '#FB7185';
            const S = st.current, sim = simRef.current;
            if (S.running) sim.t += dt;
            const t = sim.t;

            const rodX = w * 0.14, rodW = w * 0.60, rodH = 30;
            const heatCol = (temp) => {
                // temp 0..1 -> grey to amber to rose
                const r = Math.round(120 + temp * 135), g = Math.round(130 - temp * 20), b = Math.round(140 - temp * 120);
                return `rgb(${r},${g},${b})`;
            };
            RODS.forEach((rod, ri) => {
                const y = h * 0.24 + ri * h * 0.42;
                // heat front position: fraction of rod length warmed, ~ sqrt(k*t)
                const front = Math.min(1, Math.sqrt(rod.k * t) * 0.42);
                // rod body as a temperature gradient
                const seg = 40;
                for (let i = 0; i < seg; i++) {
                    const fx = i / seg;
                    // temperature at this point: high near heater, decays, only reached if within front
                    let temp = 0;
                    if (fx < front) temp = Math.max(0, (1 - fx / Math.max(front, 0.01)) * (0.4 + 0.6 * Math.min(1, t * rod.k)));
                    ctx.fillStyle = heatCol(temp);
                    ctx.fillRect(rodX + fx * rodW, y - rodH / 2, rodW / seg + 1, rodH);
                }
                ctx.strokeStyle = rod.col; ctx.lineWidth = 2;
                ctx.strokeRect(rodX, y - rodH / 2, rodW, rodH);
                // heater flame at left
                for (let f = 0; f < 3; f++) {
                    const fh = 14 + Math.sin(t * 8 + f * 2) * 4;
                    ctx.fillStyle = f % 2 ? 'rgba(251,191,36,.8)' : 'rgba(251,113,133,.7)';
                    ctx.beginPath();
                    ctx.moveTo(rodX - 6 - f * 5, y + rodH / 2 + 14);
                    ctx.quadraticCurveTo(rodX - 10 - f * 5, y + rodH / 2, rodX - 6 - f * 5, y + rodH / 2 - fh + 14);
                    ctx.quadraticCurveTo(rodX - 2 - f * 5, y + rodH / 2, rodX - 6 - f * 5, y + rodH / 2 + 14);
                    ctx.fill();
                }
                // lattice vibration row (all solids): dots vibrating, amplitude ~ local temp
                for (let i = 0; i < 22; i++) {
                    const fx = (i + 0.5) / 22;
                    let temp = fx < front ? Math.max(0, (1 - fx / Math.max(front, 0.01))) : 0;
                    const amp = 1 + temp * 4;
                    const px = rodX + fx * rodW + Math.sin(t * 12 + i * 1.9) * amp;
                    const py = y - rodH / 2 - 8 + Math.cos(t * 11 + i * 2.3) * amp;
                    ctx.fillStyle = rod.col;
                    ctx.beginPath(); ctx.arc(px, py, 2.4, 0, Math.PI * 2); ctx.fill();
                }
                // free electrons (metal only): fast dots drifting hot->cold carrying energy
                if (rod.electrons) {
                    for (let e = 0; e < 9; e++) {
                        const speed = 0.55;
                        const fx = ((t * speed + e / 9) % 1);
                        const px = rodX + fx * rodW;
                        const py = y + Math.sin(t * 20 + e * 4) * 6;
                        ctx.fillStyle = cyan;
                        ctx.beginPath(); ctx.arc(px, py, 2.8, 0, Math.PI * 2); ctx.fill();
                        // little energy glow
                        ctx.fillStyle = 'rgba(56,189,248,.25)';
                        ctx.beginPath(); ctx.arc(px, py, 5.5, 0, Math.PI * 2); ctx.fill();
                    }
                }
                // wax-stuck pins along the rod: drop when the front passes them
                for (let p = 0; p < N_PINS; p++) {
                    const fx = 0.28 + p * (0.62 / (N_PINS - 1));
                    const px = rodX + fx * rodW;
                    const dropped = front > fx;
                    const dropAmt = dropped ? Math.min(1, (front - fx) * 6) : 0;
                    const pinY = y + rodH / 2 + 4 + dropAmt * (h * 0.10);
                    ctx.save();
                    ctx.translate(px, pinY);
                    ctx.rotate(dropAmt * 1.2);
                    ctx.strokeStyle = dropped ? faint : ink; ctx.lineWidth = 2;
                    ctx.beginPath(); ctx.moveTo(0, 0); ctx.lineTo(0, 14); ctx.stroke();
                    ctx.fillStyle = dropped ? faint : amber;
                    ctx.beginPath(); ctx.arc(0, 0, 3.5, 0, Math.PI * 2); ctx.fill();
                    ctx.restore();
                    // wax blob (fades as it melts)
                    if (!dropped) {
                        ctx.fillStyle = 'rgba(251,191,36,.4)';
                        ctx.beginPath(); ctx.arc(px, y + rodH / 2 + 2, 3, 0, Math.PI * 2); ctx.fill();
                    }
                }
                // label + mechanism note + pins-fallen count
                ctx.fillStyle = ink; ctx.font = '700 11px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText(rod.label, rodX, y - rodH / 2 - 24);
                const fallen = Array.from({ length: N_PINS }, (_, p) => 0.28 + p * (0.62 / (N_PINS - 1))).filter((fx) => front > fx).length;
                ctx.fillStyle = faint; ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'right';
                ctx.fillText(`${fallen}/${N_PINS} pins fallen`, rodX + rodW, y - rodH / 2 - 24);
                ctx.fillStyle = rod.electrons ? cyan : faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText(rod.electrons
                    ? 'lattice vibrations + free (delocalised) electrons relaying energy'
                    : 'lattice vibrations only - no free electrons to carry energy',
                    rodX + 4, y + rodH / 2 + h * 0.13);
            });
            // legend
            ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText('same heater, same time - the metal\'s heat front races ahead and drops its pins first', w / 2, h - 8);

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Metal vs non-metal · heat front + mechanism</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="why the metal wins the race"
                      value="free electrons" tone="acc"
                      sub={<>both rods pass energy along their <b>vibrating lattice</b> — but only the metal also has <b>free (delocalised) electrons</b> that carry energy quickly from the hot end to the cold, so its pins fall first</>} />
                <div className="cw-btnrow">
                    <Button variant="save" onClick={() => setRunning(!running)}>{running ? '⏸ Pause' : '▶ Heat both rods'}</Button>
                    <Button variant="ghost" onClick={() => { reset(); }}>↺ Reset the race</Button>
                </div>
                <Flag kind="neutral">
                    This is the good/bad-conductor experiment: wax-stuck pins along each rod fall as the heat reaches them.
                    The metal's pins drop in quick succession; the glass's barely move. The reason is the <b>mechanism</b> —
                    lattice vibrations conduct in <b>every</b> solid, but a metal's <b>free electrons</b> add a much faster
                    delivery route the glass simply hasn't got.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, t: simRef.current.t })}>📌 Save this race to my notes</button>
                )}
            </div>
        </div>
    );
}
