import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag, Button } from '../primitives.jsx';

// ── Widget: power_lab ────────────────────────────────────────────────────────
// Bespoke Power hero simulator (5054 · 1.7.5). Two site hoists raise IDENTICAL
// crates up identical towers — the same work done — but in different times. A
// live panel prices each climb as P = W/t while the crates race up their
// cables against a dusk skyline: the quicker lift is more POWERFUL, not
// stronger. g = 9.8 N/kg. Scenario values are illustrative.
//
// Lake-bar immersion: construction site at dusk, lattice towers, winches,
// rising crates. P = W/t logic and config contract unchanged.
//
// config: { t_a, t_b } · Notes contract: getState/setState carry {t_a, t_b, t}.

const G = 9.8;
const MASS = 15;          // kg crate
const H = 4;              // tower height, m
const WORK = MASS * G * H; // same for both, ~588 J

export default function PowerLab({ config = {}, onReady, onAddToNote }) {
    const [tA, setTA] = useState(typeof config.t_a === 'number' ? config.t_a : 4);
    const [tB, setTB] = useState(typeof config.t_b === 'number' ? config.t_b : 12);
    const [running, setRunning] = useState(false);
    const [snapT, setSnapT] = useState(0);
    const simRef = useRef({ t: 0 });
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { tA, tB, running };

    const reset = () => { simRef.current.t = 0; setSnapT(0); setRunning(false); };
    useEffect(reset, [tA, tB]); // eslint-disable-line

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, t_a: st.current.tA, t_b: st.current.tB, t: simRef.current.t }),
            setState: (s) => {
                if (typeof s?.t_a === 'number') setTA(Math.max(2, Math.min(20, s.t_a)));
                if (typeof s?.t_b === 'number') setTB(Math.max(2, Math.min(20, s.t_b)));
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
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399');
            const cyan = '#38BDF8', amber = '#FBBF24';
            const S = st.current, sim = simRef.current;
            if (S.running) { sim.t += dt; setSnapT(sim.t); if (sim.t >= Math.max(S.tA, S.tB) + 0.6) setRunning(false); }

            // ── Dusk site backdrop ──
            const groundY = h * 0.84, topY = h * 0.12;
            const sky = ctx.createLinearGradient(0, 0, 0, groundY);
            sky.addColorStop(0, '#20283f'); sky.addColorStop(0.55, '#4a4266'); sky.addColorStop(1, '#c97a5c');
            ctx.fillStyle = sky; ctx.fillRect(0, 0, w, groundY);
            // low sun glow
            const sg = ctx.createRadialGradient(w * 0.68, groundY, 8, w * 0.68, groundY, h * 0.6);
            sg.addColorStop(0, 'rgba(255,200,150,.5)'); sg.addColorStop(1, 'rgba(255,200,150,0)');
            ctx.fillStyle = sg; ctx.fillRect(0, 0, w, groundY);
            // skyline silhouette
            ctx.fillStyle = 'rgba(20,22,34,.7)';
            for (let i = 0; i < 9; i++) {
                const bx = i * (w / 9) + 4, bw = w / 9 - 8, bh = 18 + (Math.sin(i * 21.3) * 0.5 + 0.5) * 44;
                ctx.fillRect(bx, groundY - bh, bw, bh);
            }
            // ground
            ctx.fillStyle = '#2a2620'; ctx.fillRect(0, groundY, w, h - groundY);

            const towers = [
                { x: w * 0.22, t: S.tA, col: cyan, name: 'hoist A' },
                { x: w * 0.46, t: S.tB, col: acc, name: 'hoist B' },
            ];
            towers.forEach(({ x, t: tt, col, name }) => {
                const f = Math.min(1, sim.t / tt);
                const crateTopY = topY + 22;
                // lattice tower
                ctx.strokeStyle = '#6b7280'; ctx.lineWidth = 3;
                ctx.beginPath(); ctx.moveTo(x - 24, groundY); ctx.lineTo(x - 24, topY); ctx.moveTo(x + 24, groundY); ctx.lineTo(x + 24, topY); ctx.stroke();
                ctx.strokeStyle = '#565c66'; ctx.lineWidth = 1.5;
                for (let yy = topY; yy < groundY; yy += 22) {
                    ctx.beginPath(); ctx.moveTo(x - 24, yy); ctx.lineTo(x + 24, yy + 11); ctx.moveTo(x + 24, yy); ctx.lineTo(x - 24, yy + 11); ctx.stroke();
                    ctx.beginPath(); ctx.moveTo(x - 24, yy); ctx.lineTo(x + 24, yy); ctx.stroke();
                }
                // winch head + pulley
                ctx.fillStyle = '#454b55'; ctx.beginPath(); ctx.roundRect(x - 20, topY - 12, 40, 14, 3); ctx.fill();
                ctx.strokeStyle = '#c9d2dc'; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.arc(x, topY + 2, 4, 0, Math.PI * 2); ctx.stroke();
                // height ticks
                ctx.font = '600 7.5px "JetBrains Mono", monospace'; ctx.textAlign = 'left'; ctx.fillStyle = faint;
                for (let m = 0; m <= H; m++) {
                    const yy = groundY - (m / H) * (groundY - crateTopY);
                    ctx.strokeStyle = 'rgba(255,255,255,.12)'; ctx.beginPath(); ctx.moveTo(x + 24, yy); ctx.lineTo(x + 29, yy); ctx.stroke();
                    ctx.fillText(`${m}m`, x + 31, yy + 2.5);
                }
                // cable + crate
                const crateY = groundY - f * (groundY - crateTopY);
                ctx.strokeStyle = '#c9d2dc'; ctx.lineWidth = 1.4;
                ctx.beginPath(); ctx.moveTo(x, topY + 2); ctx.lineTo(x, crateY - 12); ctx.stroke();
                const cg = ctx.createLinearGradient(0, crateY - 12, 0, crateY + 8);
                cg.addColorStop(0, '#caa15e'); cg.addColorStop(1, '#8a6a37');
                ctx.fillStyle = cg; ctx.beginPath(); ctx.roundRect(x - 15, crateY - 12, 30, 20, 2); ctx.fill();
                ctx.strokeStyle = 'rgba(0,0,0,.3)'; ctx.lineWidth = 1;
                ctx.beginPath(); ctx.moveTo(x - 15, crateY - 2); ctx.lineTo(x + 15, crateY - 2); ctx.moveTo(x, crateY - 12); ctx.lineTo(x, crateY + 8); ctx.stroke();
                // labels
                ctx.fillStyle = col; ctx.font = '700 10px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(name, x, groundY + 16);
                ctx.font = '700 9px "JetBrains Mono", monospace'; ctx.fillStyle = faint;
                ctx.fillText(sim.t >= tt ? `done in ${tt.toFixed(0)}s` : `${Math.min(sim.t, tt).toFixed(1)}s`, x, groundY + 28);
            });

            // ── P = W/t panel ──
            const px2 = w * 0.80;
            ctx.fillStyle = 'rgba(10,14,22,.5)'; ctx.beginPath(); ctx.roundRect(px2 - 78, topY + 4, 156, 210, 8); ctx.fill();
            ctx.font = '700 11px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            ctx.fillStyle = faint; ctx.fillText('same job, same work:', px2, topY + 26);
            ctx.fillStyle = amber; ctx.fillText(`W = mgΔh = ${Math.round(WORK)} J`, px2, topY + 44);
            [['A', S.tA, cyan], ['B', S.tB, acc]].forEach(([k, tt, col], i) => {
                const y = topY + 78 + i * 60;
                const p = WORK / tt;
                ctx.fillStyle = faint; ctx.fillText(`P(${k}) = ${Math.round(WORK)} / ${tt.toFixed(0)}`, px2, y);
                ctx.fillStyle = col; ctx.fillText(`= ${p.toFixed(0)} W`, px2, y + 18);
                ctx.fillStyle = faint; ctx.font = '600 8.5px Inter, sans-serif';
                ctx.fillText(`${p.toFixed(0)} joules every second`, px2, y + 33);
                ctx.font = '700 11px "JetBrains Mono", monospace';
            });
            const ratio = Math.max(S.tA, S.tB) / Math.min(S.tA, S.tB);
            ctx.fillStyle = amber; ctx.font = '600 9px Inter, sans-serif';
            ctx.fillText(`the quicker lift is ${ratio.toFixed(1)}× more powerful`, px2, topY + 198);
            ctx.fillText('— for exactly the same work', px2, topY + 210);

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const pA = WORK / tA, pB = WORK / tB;

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Same work · different rates</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="power = the RATE of doing work"
                      value={`${pA.toFixed(0)} vs ${pB.toFixed(0)}`} unit="W" tone="acc"
                      sub={<>both hoists do the same <b>{Math.round(WORK)} J</b> of work — P = W/t rewards the one that does it in
                          less time; a watt is one joule every second</>} />
                <Slider label="hoist A takes" value={tA} min={2} max={20} step={1} onChange={setTA} format={(x) => `${x.toFixed(0)} s`} />
                <Slider label="hoist B takes" value={tB} min={2} max={20} step={1} onChange={setTB} format={(x) => `${x.toFixed(0)} s`} />
                <div className="cw-btnrow">
                    <Button variant="save" onClick={() => setRunning(!running)}>{running ? '⏸ Pause' : '▶ Race the lifts'}</Button>
                    <Button variant="ghost" onClick={reset}>↺ Reset</Button>
                </div>
                <Flag kind="neutral">
                    The work done never changes — <b>{Math.round(WORK)} J</b> either way. What changes is the <b>time</b>, and
                    that is all power measures: <b>P = W/t = ΔE/t</b>, work done (or energy transferred) per unit time. Make the
                    times equal and the powers agree; halve a time and watch its power double.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, t_a: tA, t_b: tB, t: snapT })}>📌 Save this race to my notes</button>
                )}
            </div>
        </div>
    );
}
