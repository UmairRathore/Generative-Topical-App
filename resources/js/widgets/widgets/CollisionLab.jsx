import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag, Button } from '../primitives.jsx';

// ── Widget: collision_lab ────────────────────────────────────────────────────
// Bespoke Momentum hero simulator (5054 · 1.6). Two carts on a track collide —
// bounce apart or stick together — while a signed momentum bar chart runs
// live: each cart's p = mv swings, and the TOTAL bar never moves. After the
// collision the impulse panel shows Δp on each cart: equal size, opposite
// sign — the same force acting for the same time, both ways round.
// Outcomes are computed from conservation of momentum; the "bounce" case uses
// the standard elastic result. Scenario values are illustrative.
//
// config: { m1, u1, m2, u2, mode } · Notes contract: getState/setState carry
// {m1, u1, m2, u2, mode, phase}.

const TRACK_M = 10;                 // metres of track shown

export default function CollisionLab({ config = {}, onReady, onAddToNote }) {
    const [m1, setM1] = useState(typeof config.m1 === 'number' ? config.m1 : 2);
    const [u1, setU1] = useState(typeof config.u1 === 'number' ? config.u1 : 3);
    const [m2, setM2] = useState(typeof config.m2 === 'number' ? config.m2 : 1);
    const [u2, setU2] = useState(typeof config.u2 === 'number' ? config.u2 : -1);
    const [mode, setMode] = useState(config.mode === 'stick' ? 'stick' : 'bounce');
    const [running, setRunning] = useState(false);
    const [collided, setCollided] = useState(false);
    const simRef = useRef({ x1: 2, x2: 8, v1: 0, v2: 0 });
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { m1, u1, m2, u2, mode, running, collided };

    // post-collision velocities from conservation of momentum
    const outcome = (M1, U1, M2, U2, md) => {
        if (md === 'stick') {
            const v = (M1 * U1 + M2 * U2) / (M1 + M2);
            return [v, v];
        }
        const v1 = ((M1 - M2) * U1 + 2 * M2 * U2) / (M1 + M2);
        const v2 = ((M2 - M1) * U2 + 2 * M1 * U1) / (M1 + M2);
        return [v1, v2];
    };

    const reset = () => {
        simRef.current = { x1: 2, x2: 8, v1: u1, v2: u2 };
        setCollided(false); setRunning(false);
    };
    useEffect(reset, [m1, u1, m2, u2, mode]); // eslint-disable-line

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, m1: st.current.m1, u1: st.current.u1, m2: st.current.m2, u2: st.current.u2, mode: st.current.mode, phase: st.current.collided ? 'after' : 'before' }),
            setState: (s) => {
                if (typeof s?.m1 === 'number') setM1(Math.max(0.5, Math.min(4, s.m1)));
                if (typeof s?.u1 === 'number') setU1(Math.max(-4, Math.min(4, s.u1)));
                if (typeof s?.m2 === 'number') setM2(Math.max(0.5, Math.min(4, s.m2)));
                if (typeof s?.u2 === 'number') setU2(Math.max(-4, Math.min(4, s.u2)));
                if (s?.mode === 'stick' || s?.mode === 'bounce') setMode(s.mode);
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
            const cyan = '#38BDF8', amber = '#FBBF24';
            const S = st.current, sim = simRef.current;
            const r1 = 10 + S.m1 * 7, r2 = 10 + S.m2 * 7;        // half-widths, px-ish
            const pxPerM = (w - 60) / TRACK_M;
            const X = (m) => 30 + m * pxPerM;

            // advance
            if (S.running) {
                sim.x1 += sim.v1 * dt * 0.9; sim.x2 += sim.v2 * dt * 0.9;
                const gap = (X(sim.x2) - r2) - (X(sim.x1) + r1);
                if (!S.collided && gap <= 0 && sim.v1 - sim.v2 > 0) {
                    const [w1, w2] = outcome(S.m1, sim.v1, S.m2, sim.v2, S.mode);
                    sim.v1 = w1; sim.v2 = w2;
                    setCollided(true);
                }
                if (sim.x1 < 0.5 || sim.x1 > 9.5 || sim.x2 < 0.5 || sim.x2 > 9.5) setRunning(false);
            }

            // ── Track scene (top) ──
            const trackY = h * 0.36;
            ctx.strokeStyle = ink; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(20, trackY); ctx.lineTo(w - 20, trackY); ctx.stroke();
            const cart = (xm, r, col, v, label) => {
                const x = X(xm);
                ctx.fillStyle = col.replace(')', ',.16)').replace('rgb', 'rgba');
                ctx.fillStyle = 'rgba(56,189,248,.14)';
                ctx.strokeStyle = col; ctx.lineWidth = 2;
                const ch = 22 + r * 0.8;
                ctx.beginPath(); ctx.roundRect(x - r, trackY - ch - 8, r * 2, ch, 5); ctx.fill(); ctx.stroke();
                [-0.55, 0.55].forEach((s2) => {
                    ctx.fillStyle = ink;
                    ctx.beginPath(); ctx.arc(x + s2 * r, trackY - 8, 6, 0, Math.PI * 2); ctx.fill();
                });
                ctx.fillStyle = faint; ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(label, x, trackY - ch - 14);
                if (Math.abs(v) > 0.05) {
                    ctx.strokeStyle = amber; ctx.fillStyle = amber; ctx.lineWidth = 2.5;
                    const len = v * 13;
                    ctx.beginPath(); ctx.moveTo(x, trackY + 14); ctx.lineTo(x + len, trackY + 14); ctx.stroke();
                    const s3 = Math.sign(len);
                    ctx.beginPath(); ctx.moveTo(x + len + s3 * 7, trackY + 14);
                    ctx.lineTo(x + len, trackY + 10); ctx.lineTo(x + len, trackY + 18); ctx.fill();
                    ctx.font = '600 9px "JetBrains Mono", monospace';
                    ctx.fillText(`${v.toFixed(1)} m/s`, x + len / 2, trackY + 30);
                }
            };
            const stuck = S.collided && S.mode === 'stick';
            cart(sim.x1, r1, cyan, sim.v1, `${S.m1.toFixed(1)} kg`);
            cart(sim.x2, r2, stuck ? cyan : acc, sim.v2, `${S.m2.toFixed(1)} kg`);

            // ── Momentum bars (bottom): p1, p2, total ──
            const p1 = S.m1 * sim.v1, p2 = S.m2 * sim.v2, pt = p1 + p2;
            const gy = h * 0.56, gh = h * 0.34;
            const rows = [
                ['cart 1  p = mv', p1, cyan],
                ['cart 2  p = mv', p2, acc],
                ['TOTAL momentum', pt, amber],
            ];
            const zeroX = w * 0.52, scale = (w * 0.4) / 17;      // ±17 kg·m/s full range
            rows.forEach(([label, p, col], i) => {
                const y = gy + i * (gh / 3) + 6, bh = gh / 3 - 18;
                ctx.strokeStyle = line; ctx.lineWidth = 1;
                ctx.beginPath(); ctx.moveTo(zeroX, y - 4); ctx.lineTo(zeroX, y + bh + 4); ctx.stroke();
                ctx.fillStyle = col;
                const bw = p * scale;
                ctx.globalAlpha = 0.8;
                ctx.fillRect(Math.min(zeroX, zeroX + bw), y, Math.abs(bw), bh);
                ctx.globalAlpha = 1;
                ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'right'; ctx.fillStyle = faint;
                ctx.fillText(label, zeroX - (p >= 0 ? 8 : Math.abs(bw) + 8), y + bh / 2 + 3);
                ctx.font = '700 10.5px "JetBrains Mono", monospace'; ctx.textAlign = 'left'; ctx.fillStyle = col;
                ctx.fillText(`${p.toFixed(1)} kg m/s`, zeroX + (p >= 0 ? Math.max(bw, 0) + 8 : 8), y + bh / 2 + 3);
            });
            ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText('momentum, signed: right is positive ←|→', zeroX, gy + gh + 12);

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const [f1, f2] = outcome(m1, u1, m2, u2, mode);
    const dp1 = m1 * (f1 - u1);
    const before = m1 * u1 + m2 * u2;

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Watch the TOTAL bar</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label={collided ? 'after the collision — impulses revealed' : 'total momentum (before)'}
                      value={collided ? `Δp₁ = ${dp1.toFixed(1)}, Δp₂ = ${(-dp1).toFixed(1)}` : before.toFixed(1)}
                      unit={collided ? 'kg m/s' : 'kg m/s'} tone="acc"
                      sub={collided
                          ? <>equal size, opposite sign — the same force acted on both carts for the same time, both ways round; the total never moved</>
                          : <>the amber TOTAL bar will not move during the collision — that is the conservation of momentum, watched live</>} />
                <Slider label="cart 1 mass" value={m1} min={0.5} max={4} step={0.5} onChange={setM1} format={(x) => `${x.toFixed(1)} kg`} />
                <Slider label="cart 1 velocity" value={u1} min={-4} max={4} step={0.5} onChange={setU1} format={(x) => `${x.toFixed(1)} m/s`} />
                <Slider label="cart 2 mass" value={m2} min={0.5} max={4} step={0.5} onChange={setM2} format={(x) => `${x.toFixed(1)} kg`} />
                <Slider label="cart 2 velocity" value={u2} min={-4} max={4} step={0.5} onChange={setU2} format={(x) => `${x.toFixed(1)} m/s`} />
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'bounce' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('bounce')}>⇄ Bounce apart</button>
                    <button className={'cw-btn ' + (mode === 'stick' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('stick')}>⧉ Stick together</button>
                </div>
                <div className="cw-btnrow">
                    <Button variant="save" onClick={() => setRunning(!running)}>{running ? '⏸ Pause' : '▶ Collide'}</Button>
                    <Button variant="ghost" onClick={reset}>↺ Reset</Button>
                </div>
                <Flag kind="neutral">
                    Each cart's bar is <b>p = mv</b>, sign and all. The collision trades momentum between the carts —
                    <b> Δp₁ = −Δp₂</b> — so the amber total is the same number before, during and after. Try a heavy
                    cart sticking to a light one and predict the shared velocity before pressing Collide.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, m1, u1, m2, u2, mode })}>📌 Save this collision to my notes</button>
                )}
            </div>
        </div>
    );
}
