import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag, Button } from '../primitives.jsx';

// ── Widget: energy_park ──────────────────────────────────────────────────────
// Bespoke Energy hero simulator (5054 · 1.7.1). A skater released into a
// smooth valley: the gravitational potential store drains into the kinetic
// store and back, the TOTAL bar never moves — and switching friction on opens
// a third store (internal/thermal) that the others leak into while the total
// STILL holds. Live Ek = ½mv² and ΔEp = mgΔh readouts track the bars.
// g = 9.8 N/kg per the syllabus constant. Scenario values are illustrative.
//
// config: { h0, mass, friction } · Notes contract: getState/setState carry
// {h0, mass, friction, s}.

const G = 9.8;
const H_MAX = 5;                       // release height ceiling, m
// valley profile: h(x) for x in [0,1]; cosine bowl, depth H_MAX at edges -> 0 at centre
const hOf = (x) => (H_MAX / 2) * (1 + Math.cos(2 * Math.PI * Math.min(1, Math.max(0, x))));
const dhdx = (x) => -(H_MAX / 2) * 2 * Math.PI * Math.sin(2 * Math.PI * x);

export default function EnergyPark({ config = {}, onReady, onAddToNote }) {
    const [h0, setH0] = useState(typeof config.h0 === 'number' ? config.h0 : 4);
    const [mass, setMass] = useState(typeof config.mass === 'number' ? config.mass : 50);
    const [friction, setFriction] = useState(!!config.friction);
    const [running, setRunning] = useState(false);
    const simRef = useRef({ x: 0, dir: 1, eInt: 0 });
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { h0, mass, friction, running };

    // start position: x where h(x) = h0 on the left slope
    const xStart = (H) => Math.acos((2 * H) / H_MAX - 1) / (2 * Math.PI);

    const reset = () => { simRef.current = { x: xStart(h0), dir: 1, eInt: 0 }; setRunning(false); };
    useEffect(reset, [h0, mass, friction]); // eslint-disable-line

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, h0: st.current.h0, mass: st.current.mass, friction: st.current.friction, s: simRef.current.x }),
            setState: (s) => {
                if (typeof s?.h0 === 'number') setH0(Math.max(1, Math.min(H_MAX, s.h0)));
                if (typeof s?.mass === 'number') setMass(Math.max(20, Math.min(100, s.mass)));
                if (typeof s?.friction === 'boolean') setFriction(s.friction);
            },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, last = performance.now();
        const draw = (now) => {
            const dt = Math.min(0.04, (now - last) / 1000); last = now;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return;
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
            const cyan = '#38BDF8', amber = '#FBBF24', rose = '#FB7185';
            const S = st.current, sim = simRef.current;

            const eTotal = S.mass * G * S.h0;
            const hh = hOf(sim.x);
            let ek = Math.max(0, eTotal - S.mass * G * hh - sim.eInt);
            let v = Math.sqrt((2 * ek) / S.mass);

            if (S.running) {
                // near rest the energy-speed is ~0 and the track speed stalls: creep downhill so gravity can restart the trade
                if (v < 0.15 && Math.abs(sim.x - 0.5) > 0.01) {
                    sim.dir = sim.x < 0.5 ? 1 : -1;
                    sim.x += 0.0009 * sim.dir;
                }
                // move along track at speed v; friction leaks Ek into the internal store
                const slope = dhdx(sim.x);
                const dsdx = Math.sqrt(1 + slope * slope) * 40;          // px-ish arc factor (visual pacing)
                const dx = (v / dsdx) * dt * 2.2 * sim.dir;
                sim.x += dx;
                if (S.friction && v > 0.01) {
                    sim.eInt = Math.min(eTotal, sim.eInt + 0.10 * S.mass * G * Math.abs(dx) * 6);
                }
                // recompute; turn round where Ek runs out (track height = remaining energy)
                ek = eTotal - S.mass * G * hOf(sim.x) - sim.eInt;
                if (ek <= 0 || sim.x <= 0.02 || sim.x >= 0.98) {
                    sim.dir *= -1;
                    sim.x = Math.min(0.98, Math.max(0.02, sim.x));
                    ek = Math.max(0, ek);
                }
                if (S.friction && ek < eTotal * 0.001 && Math.abs(hOf(sim.x)) < 0.05) setRunning(false);
                v = Math.sqrt((2 * Math.max(0, ek)) / S.mass);
            }

            // ── Track scene (left ~58%) ──
            const tx = 18, tw = w * 0.56, groundY = h - 44, hillH = h * 0.62;
            const X = (x) => tx + x * tw;
            const Y = (hm) => groundY - (hm / H_MAX) * hillH;
            ctx.strokeStyle = ink; ctx.lineWidth = 2.5;
            ctx.beginPath();
            for (let x = 0; x <= 1.001; x += 0.02) {
                const px = X(x), py = Y(hOf(x));
                x === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
            }
            ctx.stroke();
            // height rule + release marker
            ctx.setLineDash([3, 4]); ctx.strokeStyle = faint; ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(X(0.5), groundY); ctx.lineTo(X(0.5), Y(H_MAX)); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(tx, Y(S.h0)); ctx.lineTo(tx + tw, Y(S.h0)); ctx.stroke(); ctx.setLineDash([]);
            ctx.fillStyle = faint; ctx.font = '600 9px "JetBrains Mono", monospace'; ctx.textAlign = 'left';
            ctx.fillText(`release h = ${S.h0.toFixed(1)} m`, tx + 4, Y(S.h0) - 6);
            // skater
            const bx = X(sim.x), by = Y(hOf(sim.x));
            ctx.fillStyle = amber;
            ctx.beginPath(); ctx.arc(bx, by - 10, 9, 0, Math.PI * 2); ctx.fill();
            ctx.strokeStyle = amber; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(bx, by - 10); ctx.lineTo(bx, by); ctx.stroke();
            // live readouts under the scene
            ctx.font = '700 10.5px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            ctx.fillStyle = cyan;
            ctx.fillText(`Ek = ½mv² = ½ × ${S.mass} × ${v.toFixed(1)}² = ${Math.round(ek)} J`, tx + tw / 2, h - 24);
            ctx.fillStyle = amber;
            ctx.fillText(`Ep above ground = mgΔh = ${S.mass} × 9.8 × ${hOf(sim.x).toFixed(1)} = ${Math.round(S.mass * G * hOf(sim.x))} J`, tx + tw / 2, h - 8);

            // ── Energy store bars (right) ──
            const ep = S.mass * G * hOf(sim.x);
            const rows = [
                ['gravitational potential store', ep, amber],
                ['kinetic store', ek, cyan],
                ['internal (thermal) store', sim.eInt, rose],
                ['TOTAL energy', ep + ek + sim.eInt, acc],
            ];
            const gx = w * 0.63, gw = w * 0.33, gy = 28, rowH = (h - 70) / 4;
            rows.forEach(([label, E, col], i) => {
                const y = gy + i * rowH;
                ctx.fillStyle = faint; ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText(label, gx, y - 5);
                ctx.strokeStyle = line; ctx.strokeRect(gx, y, gw, rowH - 26);
                const bw = Math.min(1, E / (S.mass * G * H_MAX)) * gw;
                ctx.globalAlpha = 0.85; ctx.fillStyle = col;
                ctx.fillRect(gx, y, bw, rowH - 26); ctx.globalAlpha = 1;
                ctx.fillStyle = col; ctx.font = '700 10px "JetBrains Mono", monospace';
                ctx.fillText(`${Math.round(E)} J`, gx + gw + 6 - 0, y + (rowH - 26) / 2 + 3.5);
            });
            ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'left';
            ctx.fillText(S.friction ? 'pathways: mechanical work (gravity) + heating (friction)' : 'pathway: mechanical work done by gravity',
                         gx, gy + 4 * rowH - 6);

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const eTotal = mass * G * h0;
    const vBottom = Math.sqrt(2 * G * h0);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Stores trade · the total holds</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="total energy in play (from the release height)"
                      value={Math.round(eTotal)} unit="J" tone="acc"
                      sub={friction
                          ? <>with friction on, the potential and kinetic stores leak into the <b>internal store</b> — watch the TOTAL bar stay put anyway</>
                          : <>frictionless: at the bottom the whole {Math.round(eTotal)} J sits in the kinetic store — v = <b>{vBottom.toFixed(1)} m/s</b> by ½mv² = mgΔh</>} />
                <Slider label="release height" value={h0} min={1} max={H_MAX} step={0.5} onChange={setH0} format={(x) => `${x.toFixed(1)} m`} />
                <Slider label="skater mass" value={mass} min={20} max={100} step={5} onChange={setMass} format={(x) => `${x.toFixed(0)} kg`} />
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (friction ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setFriction(!friction)}>
                        {friction ? '✓' : '+'} friction on the track
                    </button>
                    <Button variant="save" onClick={() => setRunning(!running)}>{running ? '⏸ Pause' : '▶ Release'}</Button>
                    <Button variant="ghost" onClick={reset}>↺ Reset</Button>
                </div>
                <Flag kind="neutral">
                    Two stores trade through one pathway — <b>mechanical work done by gravity</b> — and the TOTAL bar never
                    moves. Switch friction on: a third store opens, the skater eventually parks at the bottom, and the total
                    <b> still</b> never moves. Energy is not used up; it is <b>relocated</b>.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, h0, mass, friction })}>📌 Save this run to my notes</button>
                )}
            </div>
        </div>
    );
}
