import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag, Button } from '../primitives.jsx';

// ── Widget: pendulum_lab ─────────────────────────────────────────────────────
// Bespoke Measurement hero simulator (5054 · 1.1 LOs 3-4). A pendulum swings on
// a rendered clamp stand while a digital stopwatch is started and stopped BY
// HAND — every run carries a simulated reaction-time wobble. Time one swing and
// the wobble swamps the answer; time 20 and divide, and it almost vanishes.
// Each run lands as a dot on a period chart around the true value.
//
// Lake-bar immersion: lit lab scene, retort stand, metallic bob + swing trail,
// rendered stopwatch. Timing logic and dot-chart pedagogy unchanged.
//
// config: { length } · Notes contract: getState/setState carry {length, runs}.

const G = 9.8;
const REACT_S = 0.22;                     // typical human start/stop wobble (± up to this)
const periodOf = (L) => 2 * Math.PI * Math.sqrt(L / G);

export default function PendulumLab({ config = {}, onReady, onAddToNote }) {
    const [length, setLength] = useState(typeof config.length === 'number' ? config.length : 1.0);
    const [runs, setRuns] = useState([]);            // {n, total, period}
    const [timing, setTiming] = useState(null);      // {n, tEnd, tShown}
    const cvRef = useRef(null);
    const phaseRef = useRef(0);
    const st = useRef({}); st.current = { length, runs, timing };

    const T = periodOf(length);

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, length: st.current.length, runs: st.current.runs }),
            setState: (s) => {
                if (typeof s?.length === 'number') setLength(Math.max(0.3, Math.min(1.5, s.length)));
                if (Array.isArray(s?.runs)) setRuns(s.runs);
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
            const faint = cssVar('--ink-faint', '#68877A');
            const line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
            const cyan = '#38BDF8', amber = '#FBBF24';
            const S = st.current;
            const TT = periodOf(S.length);
            phaseRef.current += dt;

            if (S.timing) {
                S.timing.tShown = Math.min(S.timing.tEnd, (S.timing.tShown ?? 0) + dt * 6);
                if (S.timing.tShown >= S.timing.tEnd) {
                    const done = S.timing;
                    setRuns((r) => [...r.slice(-11), { n: done.n, total: done.tEnd, period: done.tEnd / done.n }]);
                    setTiming(null);
                }
            }

            // ── Lab room + bench ──
            const wall = ctx.createLinearGradient(0, 0, 0, h);
            wall.addColorStop(0, '#1f252d'); wall.addColorStop(0.6, '#262c34'); wall.addColorStop(1, '#1e2429');
            ctx.fillStyle = wall; ctx.fillRect(0, 0, w, h);
            const glow = ctx.createRadialGradient(w * 0.25, h * 0.12, 10, w * 0.25, h * 0.12, h);
            glow.addColorStop(0, 'rgba(210,225,240,.08)'); glow.addColorStop(1, 'rgba(210,225,240,0)');
            ctx.fillStyle = glow; ctx.fillRect(0, 0, w, h);
            const benchY = h - 26;
            const bench = ctx.createLinearGradient(0, benchY, 0, h);
            bench.addColorStop(0, '#373f49'); bench.addColorStop(1, '#282e36');
            ctx.fillStyle = bench; ctx.fillRect(0, benchY, w, h - benchY);

            // ── Retort stand ──
            const px = w * 0.26, py = 34;
            const rodX = px - 46;
            // base
            const baseG = ctx.createLinearGradient(0, benchY - 8, 0, benchY);
            baseG.addColorStop(0, '#b8bfc8'); baseG.addColorStop(1, '#7f8791');
            ctx.fillStyle = baseG; ctx.beginPath(); ctx.roundRect(rodX - 30, benchY - 8, 76, 8, 3); ctx.fill();
            // vertical rod
            const rodG = ctx.createLinearGradient(rodX - 3, 0, rodX + 3, 0);
            rodG.addColorStop(0, '#8a929c'); rodG.addColorStop(0.5, '#d3dae1'); rodG.addColorStop(1, '#8a929c');
            ctx.fillStyle = rodG; ctx.fillRect(rodX - 3, py - 12, 6, benchY - py + 12);
            // boss head + horizontal arm to pivot
            ctx.fillStyle = '#5b626b'; ctx.beginPath(); ctx.roundRect(rodX - 6, py - 14, 14, 16, 3); ctx.fill();
            ctx.fillStyle = rodG; ctx.fillRect(rodX, py - 4, px - rodX + 4, 6);
            // clamp jaw at pivot
            ctx.fillStyle = '#5b626b'; ctx.beginPath(); ctx.arc(px, py, 5, 0, Math.PI * 2); ctx.fill();

            // ── Pendulum ──
            const Lpx = 40 + S.length * ((h - 150) / 1.5);
            const ang = (ph) => 0.42 * Math.cos((2 * Math.PI * ph) / TT);
            const th = ang(phaseRef.current);
            const bx = px + Math.sin(th) * Lpx, by = py + Math.cos(th) * Lpx;
            // swing envelope arc (faint)
            ctx.strokeStyle = line; ctx.lineWidth = 1; ctx.setLineDash([2, 5]);
            ctx.beginPath(); ctx.arc(px, py, Lpx, Math.PI / 2 - 0.42, Math.PI / 2 + 0.42); ctx.stroke(); ctx.setLineDash([]);
            // motion-blur ghost bobs
            for (let k = 1; k <= 3; k++) {
                const tg = ang(phaseRef.current - k * 0.045);
                const gx2 = px + Math.sin(tg) * Lpx, gy2 = py + Math.cos(tg) * Lpx;
                ctx.fillStyle = `rgba(251,191,36,${0.18 / k})`;
                ctx.beginPath(); ctx.arc(gx2, gy2, 11, 0, Math.PI * 2); ctx.fill();
            }
            // string
            ctx.strokeStyle = '#c9d2dc'; ctx.lineWidth = 1.4;
            ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(bx, by); ctx.stroke();
            // metallic bob
            const bobG = ctx.createRadialGradient(bx - 4, by - 4, 1, bx, by, 12);
            bobG.addColorStop(0, '#ffe9b0'); bobG.addColorStop(0.5, '#f5b942'); bobG.addColorStop(1, '#9c7414');
            ctx.fillStyle = bobG;
            ctx.beginPath(); ctx.arc(bx, by, 11, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = 'rgba(255,255,255,.5)';
            ctx.beginPath(); ctx.arc(bx - 4, by - 4, 3, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = faint; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText(`length ${S.length.toFixed(2)} m`, px, benchY - 12);
            ctx.fillText('1 oscillation = a full there-and-back', px, 16);

            // ── Digital stopwatch (rendered handheld) ──
            const swX = px, swY = h - 44;
            ctx.fillStyle = '#2b3038'; ctx.beginPath(); ctx.roundRect(swX - 52, swY - 4, 104, 32, 7); ctx.fill();
            ctx.strokeStyle = 'rgba(255,255,255,.1)'; ctx.lineWidth = 1; ctx.stroke();
            ctx.fillStyle = '#0d130f'; ctx.beginPath(); ctx.roundRect(swX - 44, swY, 88, 18, 3); ctx.fill();
            ctx.fillStyle = S.timing ? amber : '#4ef0a8';
            ctx.font = '700 15px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            ctx.fillText((S.timing ? S.timing.tShown : (S.runs.at(-1)?.total ?? 0)).toFixed(2) + ' s', swX, swY + 14);
            ctx.fillStyle = faint; ctx.font = '600 8px Inter, sans-serif';
            ctx.fillText(S.timing ? `timing ${S.timing.n} oscillation${S.timing.n > 1 ? 's' : ''}…` : 'digital stopwatch', swX, swY + 26);

            // ── Period chart (right) ──
            const gx = w * 0.47, gw = w * 0.49, gy = 34, gh = h - 96;
            ctx.fillStyle = 'rgba(160,200,175,.03)'; ctx.beginPath(); ctx.roundRect(gx - 4, gy - 20, gw + 12, gh + 44, 8); ctx.fill();
            const tMin = TT - 0.45, tMax = TT + 0.45;
            const X = (tt) => gx + ((tt - tMin) / (tMax - tMin)) * gw;
            ctx.strokeStyle = cssVar('--ink-soft', '#A6C4B3'); ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.moveTo(gx, gy + gh); ctx.lineTo(gx + gw, gy + gh); ctx.stroke();
            ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText('period estimate / s →', gx + gw / 2, gy + gh + 26);
            ctx.font = '600 8px "JetBrains Mono", monospace';
            for (let tt = Math.ceil(tMin * 10) / 10; tt <= tMax; tt += 0.2) {
                ctx.strokeStyle = line; ctx.beginPath(); ctx.moveTo(X(tt), gy + gh); ctx.lineTo(X(tt), gy + gh + 5); ctx.stroke();
                ctx.fillStyle = faint; ctx.fillText(tt.toFixed(1), X(tt), gy + gh + 14);
            }
            ctx.setLineDash([4, 4]); ctx.strokeStyle = acc; ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.moveTo(X(TT), gy); ctx.lineTo(X(TT), gy + gh); ctx.stroke(); ctx.setLineDash([]);
            ctx.fillStyle = acc; ctx.font = '600 9px Inter, sans-serif';
            ctx.fillText('true period', X(TT), gy - 6);
            const lanes = [{ n: 1, y: gy + gh * 0.25, label: 'timed 1 swing' },
                           { n: 10, y: gy + gh * 0.55, label: 'timed 10 ÷ 10' },
                           { n: 20, y: gy + gh * 0.82, label: 'timed 20 ÷ 20' }];
            lanes.forEach((ln) => {
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText(ln.label, gx + 4, ln.y - 12);
                ctx.strokeStyle = line; ctx.beginPath(); ctx.moveTo(gx, ln.y); ctx.lineTo(gx + gw, ln.y); ctx.stroke();
                S.runs.filter((r) => r.n === ln.n).forEach((r) => {
                    const xx = Math.max(gx, Math.min(gx + gw, X(r.period)));
                    ctx.fillStyle = ln.n === 1 ? '#FB7185' : ln.n === 10 ? cyan : acc;
                    ctx.globalAlpha = 0.85;
                    ctx.beginPath(); ctx.arc(xx, ln.y, 4.5, 0, Math.PI * 2); ctx.fill();
                    ctx.globalAlpha = 1;
                });
            });
            ctx.textAlign = 'center';

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const timeRun = (n) => {
        if (timing) return;
        const wobble = () => (Math.random() * 2 - 1) * REACT_S;
        const total = Math.max(0.1, n * T + wobble() + wobble());
        setTiming({ n, tEnd: total, tShown: 0 });
    };
    const latest = runs.at(-1);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Measure many · divide once</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label={latest ? `last run: ${latest.n} oscillation${latest.n > 1 ? 's' : ''} in ${latest.total.toFixed(2)} s` : 'no runs yet'}
                      value={latest ? (latest.total / latest.n).toFixed(latest.n === 1 ? 2 : 3) : '—'}
                      unit={latest ? 's per oscillation' : ''} tone="acc"
                      sub={<>your hands add roughly ±{REACT_S.toFixed(2)} s at the start AND the stop — dividing by more
                          oscillations shrinks what that wobble does to the <b>period</b></>} />
                <Slider label="pendulum length" value={length} min={0.3} max={1.5} step={0.05}
                        onChange={(x) => { setLength(x); setRuns([]); }} format={(x) => `${x.toFixed(2)} m`} />
                <div className="cw-btnrow">
                    <Button variant="ghost" onClick={() => timeRun(1)}>⏱ Time 1 swing</Button>
                    <Button variant="ghost" onClick={() => timeRun(10)}>⏱ Time 10 swings</Button>
                    <Button variant="save" onClick={() => timeRun(20)}>⏱ Time 20 swings</Button>
                </div>
                <div className="cw-btnrow">
                    <Button variant="ghost" onClick={() => setRuns([])}>↺ Clear runs</Button>
                </div>
                <Flag kind="neutral">
                    Repeat each button several times and compare the three rows of dots: the single-swing estimates scatter
                    widely; the ÷20 estimates hug the true-period line. Same timer, same hands — the trick is
                    <b> measuring multiples</b>, then dividing.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, length, runs })}>📌 Save these timings to my notes</button>
                )}
            </div>
        </div>
    );
}
