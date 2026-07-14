import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag, Button } from '../primitives.jsx';

// ── Widget: efficiency_lab ───────────────────────────────────────────────────
// Bespoke Efficiency hero simulator (5054 · 1.7.4). An electric winch lifts a
// crate up a shaft: the input band (electrical) visibly splits into a useful
// band (the crate's mgΔh) and a waste band (heating), and the % efficiency
// readout computes BOTH syllabus forms live - useful energy / total energy and
// useful power / total power - which stay equal, frame after frame.
// g = 9.8 N/kg. Scenario values are illustrative.
//
// config: { waste, input_power } · Notes contract: getState/setState carry
// {waste, t}.

const G = 9.8;
const MASS = 20;          // kg crate
const H_MAX = 6;          // shaft height, m

export default function EfficiencyLab({ config = {}, onReady, onAddToNote }) {
    const [waste, setWaste] = useState(typeof config.waste === 'number' ? Math.min(0.9, Math.max(0.1, config.waste)) : 0.4);
    const [inputPower] = useState(typeof config.input_power === 'number' ? config.input_power : 200);
    const [running, setRunning] = useState(false);
    const [snap, setSnap] = useState({ t: 0, h: 0 });
    const simRef = useRef({ t: 0, h: 0 });
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { waste, running };

    const reset = () => { simRef.current = { t: 0, h: 0 }; setSnap({ t: 0, h: 0 }); setRunning(false); };
    useEffect(reset, [waste]); // eslint-disable-line

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, waste: st.current.waste, t: simRef.current.t }),
            setState: (s) => { if (typeof s?.waste === 'number') setWaste(Math.min(0.9, Math.max(0.1, s.waste))); },
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
            const pUseful = inputPower * (1 - S.waste);
            const vLift = pUseful / (MASS * G);                     // steady lifting speed

            if (S.running) {
                sim.t += dt;
                sim.h = Math.min(H_MAX, sim.h + vLift * dt);
                setSnap({ t: sim.t, h: sim.h });
                if (sim.h >= H_MAX) setRunning(false);
            }
            const eIn = inputPower * sim.t;
            const eUse = MASS * G * sim.h;
            const eWaste = Math.max(0, eIn - eUse);

            // ── Shaft + crate (left ~38%) ──
            const sx = w * 0.16, groundY = h - 40, topY = 26;
            const Y = (hm) => groundY - (hm / H_MAX) * (groundY - topY - 14);
            ctx.strokeStyle = ink; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(sx - 34, groundY); ctx.lineTo(sx - 34, topY); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(sx + 34, groundY); ctx.lineTo(sx + 34, topY); ctx.stroke();
            ctx.font = '600 8px "JetBrains Mono", monospace'; ctx.fillStyle = faint; ctx.textAlign = 'left';
            for (let m = 0; m <= H_MAX; m += 1) {
                ctx.strokeStyle = line; ctx.beginPath(); ctx.moveTo(sx + 34, Y(m)); ctx.lineTo(sx + 40, Y(m)); ctx.stroke();
                ctx.fillText(`${m} m`, sx + 43, Y(m) + 2.5);
            }
            // winch at top + rope
            ctx.fillStyle = ink;
            ctx.beginPath(); ctx.arc(sx, topY, 7, 0, Math.PI * 2); ctx.fill();
            ctx.strokeStyle = faint; ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.moveTo(sx, topY); ctx.lineTo(sx, Y(sim.h) - 24); ctx.stroke();
            // crate
            ctx.fillStyle = 'rgba(56,189,248,.14)'; ctx.strokeStyle = cyan; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.roundRect(sx - 20, Y(sim.h) - 24, 40, 24, 5); ctx.fill(); ctx.stroke();
            ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText(`${MASS} kg`, sx, Y(sim.h) + 14);
            // heat shimmer from the winch, sized by waste
            const shim = 6 + S.waste * 26;
            for (let i = 0; i < 3; i++) {
                const p = ((sim.t * 0.7 + i / 3) % 1);
                ctx.strokeStyle = `rgba(251,113,133,${0.55 * (1 - p)})`; ctx.lineWidth = 2;
                ctx.beginPath();
                const yy = topY - 4 - p * shim;
                ctx.moveTo(sx + 12, yy); ctx.quadraticCurveTo(sx + 17, yy - 4, sx + 22, yy);
                ctx.stroke();
            }

            // ── Energy split bands (middle) ──
            const bx = w * 0.34, bw = w * 0.30;
            const bandY0 = h * 0.30, bandH = h * 0.30;
            const eMax = inputPower * (H_MAX / (inputPower * 0.1 / (MASS * G)));   // loose scale
            const scale = Math.max(eIn, MASS * G * H_MAX / (1 - 0.9)) || 1;
            const bH = (E) => Math.max(0, (E / Math.max(eIn, 1)) * bandH);
            // input band entering
            ctx.fillStyle = 'rgba(56,189,248,.35)';
            ctx.fillRect(bx, bandY0, bw * 0.32, bandH);
            ctx.fillStyle = cyan; ctx.font = '700 10px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            ctx.fillText(`input ${Math.round(eIn)} J`, bx + bw * 0.16, bandY0 - 8);
            // split: useful (up-right, green), waste (down-right, rose)
            const splitX = bx + bw * 0.32;
            const uH = bandH * (1 - S.waste), wH = bandH * S.waste;
            ctx.fillStyle = 'rgba(52,211,153,.4)';
            ctx.beginPath();
            ctx.moveTo(splitX, bandY0);
            ctx.lineTo(splitX + bw * 0.6, bandY0 - 24);
            ctx.lineTo(splitX + bw * 0.6, bandY0 - 24 + uH);
            ctx.lineTo(splitX, bandY0 + uH);
            ctx.closePath(); ctx.fill();
            ctx.fillStyle = acc;
            ctx.fillText(`useful ${Math.round(eUse)} J`, splitX + bw * 0.34, bandY0 - 32);
            ctx.fillStyle = 'rgba(251,113,133,.35)';
            ctx.beginPath();
            ctx.moveTo(splitX, bandY0 + uH);
            ctx.lineTo(splitX + bw * 0.6, bandY0 + uH + 34);
            ctx.lineTo(splitX + bw * 0.6, bandY0 + uH + 34 + wH);
            ctx.lineTo(splitX, bandY0 + bandH);
            ctx.closePath(); ctx.fill();
            ctx.fillStyle = rose;
            ctx.fillText(`wasted (heating) ${Math.round(eWaste)} J`, splitX + bw * 0.34, bandY0 + uH + 34 + wH + 16);

            // ── Both syllabus forms, live (right) ──
            const px2 = w * 0.84;
            const effE = eIn > 0 ? (eUse / eIn) * 100 : (1 - S.waste) * 100;
            const effP = (pUseful / inputPower) * 100;
            ctx.font = '700 11px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            ctx.fillStyle = faint; ctx.fillText('efficiency =', px2, h * 0.22);
            ctx.fillStyle = acc;
            ctx.fillText(`useful energy / total energy`, px2, h * 0.22 + 18);
            ctx.fillText(`= ${Math.round(eUse)} / ${Math.round(eIn)} = ${eIn > 0 ? effE.toFixed(0) : '—'}%`, px2, h * 0.22 + 36);
            ctx.fillStyle = faint; ctx.fillText('and equally:', px2, h * 0.22 + 62);
            ctx.fillStyle = amber;
            ctx.fillText(`useful power / total power`, px2, h * 0.22 + 80);
            ctx.fillText(`= ${Math.round(pUseful)} W / ${inputPower} W = ${effP.toFixed(0)}%`, px2, h * 0.22 + 98);
            ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif';
            ctx.fillText('two forms, one number - never above 100%', px2, h * 0.22 + 122);

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const effPct = Math.round((1 - waste) * 100);
    const eUse = MASS * G * snap.h, eIn = inputPower * snap.t;

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Useful over total · × 100%</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="efficiency of this winch"
                      value={effPct} unit="%" tone={effPct >= 60 ? 'acc' : 'warn'}
                      sub={<>input {inputPower} W splits into <b>{Math.round(inputPower * (1 - waste))} W useful</b> (lifting) + <b>{Math.round(inputPower * waste)} W wasted</b> (heating) — lifted energy so far: {Math.round(eUse)} J of {Math.round(eIn)} J supplied</>} />
                <Slider label="energy wasted in the winch (friction, motor heating)" value={waste} min={0.1} max={0.9} step={0.05}
                        onChange={setWaste} format={(x) => `${Math.round(x * 100)}% wasted`} />
                <div className="cw-btnrow">
                    <Button variant="save" onClick={() => setRunning(!running)}>{running ? '⏸ Pause' : '▶ Lift the crate'}</Button>
                    <Button variant="ghost" onClick={reset}>↺ Reset</Button>
                </div>
                <Flag kind="neutral">
                    Watch both readouts: <b>useful energy / total energy</b> and <b>useful power / total power</b> give the
                    <b> same percentage</b> — power is just the energy ratio per second. Wasted never means destroyed: the
                    missing joules leave as <b>heating</b>, and useful + wasted always equals the input.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, waste, t: snap.t })}>📌 Save this lift to my notes</button>
                )}
            </div>
        </div>
    );
}
