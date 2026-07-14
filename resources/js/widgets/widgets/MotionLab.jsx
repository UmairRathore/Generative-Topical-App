import React, { useEffect, useMemo, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag, Button, Slider } from '../primitives.jsx';

// ── Widget: motion_lab ───────────────────────────────────────────────────────
// Bespoke Motion Graphs hero simulator (5054 · 1.2). A cyclist rides through a
// fully rendered dawn landscape — parallax sky, hills, drifting clouds and a
// scrolling road whose roadside markers stream past to make speed felt, not
// stated — while BOTH motion graphs (distance–time and speed–time) draw
// themselves live beneath, with a synchronized cursor and a live motion-state
// tag. The same instant is always visible in three representations at once.
//
// Lake-bar immersion (physical-scenario tier): the scene is a rendered world,
// not an instrument strip; the twin graphs remain the pedagogical core beneath.
//
// config: { scenario: 'full'|'steady'|'sprint'|'brake'|'thereAndWait', t }
// Notes contract: getState()/setState() carry { scenario, t }.

const SCENARIOS = {
    steady: {
        label: 'Steady ride',
        blurb: 'One unchanging speed: both graphs are straight — but they say different things.',
        segs: [{ dur: 12, v0: 5, v1: 5 }],
    },
    sprint: {
        label: 'Sprint & cruise',
        blurb: 'Speeds up from rest, then holds the pace.',
        segs: [{ dur: 5, v0: 0, v1: 8 }, { dur: 7, v0: 8, v1: 8 }],
    },
    brake: {
        label: 'Brake to a stop',
        blurb: 'Cruise, brake smoothly, stand still — watch which graph goes flat at zero.',
        segs: [{ dur: 4, v0: 7, v1: 7 }, { dur: 4, v0: 7, v1: 0 }, { dur: 4, v0: 0, v1: 0 }],
    },
    thereAndWait: {
        label: 'Ride & wait',
        blurb: 'A ride, a wait — the distance line holds its height while the speed line sits on zero.',
        segs: [{ dur: 3, v0: 0, v1: 6 }, { dur: 4, v0: 6, v1: 6 }, { dur: 2, v0: 6, v1: 0 }, { dur: 3, v0: 0, v1: 0 }],
    },
    full: {
        label: 'Full journey',
        blurb: 'Accelerate, cruise, brake, wait — all four motion states in one trip.',
        segs: [{ dur: 3, v0: 0, v1: 6 }, { dur: 4, v0: 6, v1: 6 }, { dur: 2, v0: 6, v1: 0 }, { dur: 3, v0: 0, v1: 0 }],
    },
};

// Piecewise-linear speed profile → v(t), s(t) by exact trapezoid integration.
function profile(segs) {
    const marks = [];
    let t0 = 0, s0 = 0;
    for (const seg of segs) {
        marks.push({ t0, t1: t0 + seg.dur, v0: seg.v0, v1: seg.v1, s0 });
        s0 += ((seg.v0 + seg.v1) / 2) * seg.dur;
        t0 += seg.dur;
    }
    const T = t0, S = s0, vMax = Math.max(...segs.flatMap((s) => [s.v0, s.v1]));
    const at = (t) => {
        const tt = Math.max(0, Math.min(T, t));
        const m = marks.find((k) => tt <= k.t1) || marks[marks.length - 1];
        const f = (tt - m.t0) / (m.t1 - m.t0 || 1);
        const v = m.v0 + (m.v1 - m.v0) * f;
        const s = m.s0 + ((m.v0 + v) / 2) * (tt - m.t0);
        const a = (m.v1 - m.v0) / (m.t1 - m.t0 || 1);
        return { v, s, a };
    };
    return { T, S, vMax: vMax || 1, at };
}

function stateOf(v, a) {
    if (Math.abs(a) < 0.01) return v < 0.05 ? { txt: 'at rest', col: '#9ca3af' } : { txt: 'constant speed', col: '#34D399' };
    return a > 0 ? { txt: 'speeding up — accelerating', col: '#38BDF8' } : { txt: 'slowing down — decelerating', col: '#FBBF24' };
}

// deterministic pseudo-random for scenery placement (no Math.random)
const rnd = (i, k = 1) => ((Math.sin(i * 12.9898 + k * 78.233) * 43758.5453) % 1 + 1) % 1;

const PX_PER_M = 11;   // world metres → screen px on the ground layer

export default function MotionLab({ config = {}, onReady, onAddToNote }) {
    const [scenario, setScenario] = useState(SCENARIOS[config.scenario] ? config.scenario : 'full');
    const prof = useMemo(() => profile(SCENARIOS[scenario].segs), [scenario]);
    const [t, setT] = useState(() => Math.max(0, Math.min(prof.T, config.t ?? 0)));
    const [playing, setPlaying] = useState(false);
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { t, playing, prof, scenario };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, scenario, t: st.current.t }),
            setState: (s) => {
                if (s?.scenario && SCENARIOS[s.scenario]) setScenario(s.scenario);
                if (typeof s?.t === 'number') setT(s.t);
            },
        });
    }, [onReady, scenario]); // eslint-disable-line

    // Play loop.
    useEffect(() => {
        if (!playing) return undefined;
        let raf, last = performance.now();
        const tick = (now) => {
            const dt = Math.min(0.05, (now - last) / 1000); last = now;
            setT((prev) => {
                const next = prev + dt;
                if (next >= st.current.prof.T) { setPlaying(false); return st.current.prof.T; }
                return next;
            });
            raf = requestAnimationFrame(tick);
        };
        raf = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(raf);
    }, [playing]);

    const { v, s, a } = prof.at(t);
    const state = stateOf(v, a);

    // ── Rendered world + twin live graphs, one RAF-driven canvas ──────────────
    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, start = performance.now();
        const draw = (nowMs) => {
            const amb = (nowMs - start) / 1000;                       // ambient clock (drift, pedalling idle)
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);

            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
            const cyan = '#38BDF8', amber = '#FBBF24';
            const { T, S, vMax, at } = st.current.prof;
            const tt = st.current.t;
            const { v: vv, s: ss, a: aa } = at(tt);
            const stt = stateOf(vv, aa);

            // ── SCENE (rendered dawn landscape) ──
            const sceneH = Math.round(h * 0.56);
            const groundY = Math.round(sceneH * 0.86);
            ctx.save();
            ctx.beginPath(); ctx.roundRect(0, 0, w, sceneH, 12); ctx.clip();

            // sky: night-blue high → warm peach at the horizon
            const sky = ctx.createLinearGradient(0, 0, 0, sceneH);
            sky.addColorStop(0, '#16233b'); sky.addColorStop(0.5, '#2f4a6b');
            sky.addColorStop(0.78, '#6b6f8f'); sky.addColorStop(1, '#e6a274');
            ctx.fillStyle = sky; ctx.fillRect(0, 0, w, sceneH);

            // low sun with soft glow
            const sunX = w * 0.80, sunY = sceneH * 0.60;
            const glow = ctx.createRadialGradient(sunX, sunY, 4, sunX, sunY, sceneH * 0.55);
            glow.addColorStop(0, 'rgba(255,214,170,.85)'); glow.addColorStop(0.25, 'rgba(255,190,130,.35)');
            glow.addColorStop(1, 'rgba(255,190,130,0)');
            ctx.fillStyle = glow; ctx.fillRect(0, 0, w, sceneH);
            ctx.fillStyle = 'rgba(255,236,205,.95)';
            ctx.beginPath(); ctx.arc(sunX, sunY, 15, 0, Math.PI * 2); ctx.fill();

            // drifting clouds (slow parallax + ambient drift)
            const cloud = (cx, cy, sc, alpha) => {
                ctx.fillStyle = `rgba(230,214,224,${alpha})`;
                [[0, 0, 1], [14 * sc, 2 * sc, 0.8], [-13 * sc, 3 * sc, 0.7], [6 * sc, -4 * sc, 0.7]].forEach(([dx, dy, r]) => {
                    ctx.beginPath(); ctx.ellipse(cx + dx, cy + dy, 15 * sc * r, 8 * sc * r, 0, 0, Math.PI * 2); ctx.fill();
                });
            };
            for (let i = 0; i < 4; i++) {
                const span = w + 160;
                const cx = ((rnd(i, 3) * span - ss * PX_PER_M * 0.06 - amb * 6) % span + span) % span - 80;
                cloud(cx, sceneH * (0.14 + rnd(i, 4) * 0.16), 0.7 + rnd(i, 5) * 0.5, 0.16 + rnd(i, 6) * 0.12);
            }

            // far hills (slow parallax)
            const hillLayer = (baseY, amp, colr, par, seed) => {
                ctx.fillStyle = colr; ctx.beginPath(); ctx.moveTo(0, sceneH);
                const off = ss * PX_PER_M * par;
                for (let x = -40; x <= w + 40; x += 20) {
                    const wx = x + off;
                    const y = baseY - amp * (0.5 + 0.5 * Math.sin(wx * 0.008 + seed) + 0.3 * Math.sin(wx * 0.021 + seed * 2));
                    ctx.lineTo(x, y);
                }
                ctx.lineTo(w, sceneH); ctx.closePath(); ctx.fill();
            };
            hillLayer(sceneH * 0.66, 26, 'rgba(60,80,110,.55)', 0.10, 1.2);
            hillLayer(sceneH * 0.74, 34, 'rgba(46,66,86,.8)', 0.22, 4.7);

            // ground + road
            ctx.fillStyle = '#2b3a2e'; ctx.fillRect(0, groundY, w, sceneH - groundY);         // grass verge
            ctx.fillStyle = '#3a3f47'; ctx.fillRect(0, groundY, w, 16);                        // asphalt band
            ctx.strokeStyle = '#59606b'; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(0, groundY); ctx.lineTo(w, groundY); ctx.stroke();
            // centre-line dashes scrolling with distance
            ctx.strokeStyle = 'rgba(240,224,150,.7)'; ctx.lineWidth = 2.5; ctx.setLineDash([14, 12]);
            ctx.lineDashOffset = (ss * PX_PER_M) % 26;
            ctx.beginPath(); ctx.moveTo(0, groundY + 9); ctx.lineTo(w, groundY + 9); ctx.stroke(); ctx.setLineDash([]);

            // roadside markers every 5 m → real distance read, streaming past = speed felt
            ctx.font = '600 8px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            const originX = w * 0.30;
            for (let m = Math.floor((ss - 6)); m <= ss + (w - originX) / PX_PER_M + 6; m++) {
                if (m < 0 || m % 5 !== 0) continue;
                const sx = originX + (m - ss) * PX_PER_M;
                if (sx < -20 || sx > w + 20) continue;
                ctx.strokeStyle = line; ctx.lineWidth = 1;
                ctx.beginPath(); ctx.moveTo(sx, groundY + 16); ctx.lineTo(sx, groundY + 22); ctx.stroke();
                ctx.fillStyle = faint; ctx.fillText(`${m}m`, sx, groundY + 32);
            }
            // roadside trees every ~9 m behind the road
            for (let k = -1; k < (w / PX_PER_M) / 9 + 3; k++) {
                const baseM = Math.floor(ss / 9) * 9 + k * 9;
                const sx = originX + (baseM - ss) * PX_PER_M + (rnd(baseM, 7) - 0.5) * 24;
                if (sx < -30 || sx > w + 30) continue;
                const th = 20 + rnd(baseM, 8) * 14;
                ctx.strokeStyle = '#3b2e26'; ctx.lineWidth = 3;
                ctx.beginPath(); ctx.moveTo(sx, groundY - 2); ctx.lineTo(sx, groundY - 2 - th); ctx.stroke();
                ctx.fillStyle = 'rgba(58,92,64,.9)';
                ctx.beginPath(); ctx.ellipse(sx, groundY - 4 - th, 11 + rnd(baseM, 9) * 5, 13 + rnd(baseM, 2) * 5, 0, 0, Math.PI * 2); ctx.fill();
            }

            // speed lines behind the rider (only when moving briskly)
            if (vv > 3.5) {
                ctx.strokeStyle = `rgba(255,255,255,${Math.min(0.28, (vv - 3.5) * 0.06)})`; ctx.lineWidth = 1.5;
                for (let i = 0; i < 5; i++) {
                    const ly = groundY - 10 - i * 6 - (amb * 60 % 12);
                    const lx = originX - 12 - ((amb * vv * 40 + i * 40) % 60);
                    ctx.beginPath(); ctx.moveTo(lx, ly); ctx.lineTo(lx - 16 - vv * 2, ly); ctx.stroke();
                }
            }

            // ── the cyclist (fixed screen x; the world scrolls behind) ──
            const cxr = originX, cyr = groundY - 4;
            const pedal = amb * vv * 2.2;                              // legs pedal with speed
            const spin = (ss * PX_PER_M) / 7;                          // wheels roll with distance
            const bob = vv > 0.1 ? Math.sin(pedal * 2) * 0.8 : 0;
            const rimCol = stt.col;
            const drawWheel = (wx) => {
                ctx.strokeStyle = '#c9d2dc'; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.arc(wx, cyr - 8, 9, 0, Math.PI * 2); ctx.stroke();
                ctx.strokeStyle = 'rgba(201,210,220,.5)'; ctx.lineWidth = 1;
                for (let sp = 0; sp < 4; sp++) {
                    const ang = spin + sp * Math.PI / 2;
                    ctx.beginPath(); ctx.moveTo(wx, cyr - 8);
                    ctx.lineTo(wx + Math.cos(ang) * 8, cyr - 8 + Math.sin(ang) * 8); ctx.stroke();
                }
            };
            drawWheel(cxr - 13); drawWheel(cxr + 13);
            // frame
            ctx.strokeStyle = rimCol; ctx.lineWidth = 2.4;
            ctx.beginPath();
            ctx.moveTo(cxr - 13, cyr - 8); ctx.lineTo(cxr + 2, cyr - 8);       // bottom
            ctx.lineTo(cxr - 4, cyr - 22); ctx.lineTo(cxr - 13, cyr - 8);      // seat tube triangle
            ctx.moveTo(cxr + 2, cyr - 8); ctx.lineTo(cxr - 4, cyr - 22);       // down tube
            ctx.moveTo(cxr + 13, cyr - 8); ctx.lineTo(cxr + 6, cyr - 20);      // fork
            ctx.stroke();
            // rider: torso, head/helmet, pedalling legs, arms to bars
            const hipX = cxr - 4, hipY = cyr - 22 + bob, shX = cxr + 3, shY = cyr - 34 + bob;
            ctx.strokeStyle = rimCol; ctx.lineWidth = 2.6;
            ctx.beginPath(); ctx.moveTo(hipX, hipY); ctx.lineTo(shX, shY); ctx.stroke();     // torso lean-forward
            // legs to crank
            const crankX = cxr - 2, crankY = cyr - 8;
            [0, Math.PI].forEach((ph) => {
                const kx = crankX + Math.cos(pedal + ph) * 5, ky = crankY + Math.sin(pedal + ph) * 5;
                ctx.strokeStyle = 'rgba(52,211,153,.7)'; ctx.lineWidth = 2.2;
                ctx.beginPath(); ctx.moveTo(hipX, hipY); ctx.lineTo((hipX + kx) / 2 + 2, (hipY + ky) / 2 - 3); ctx.lineTo(kx, ky); ctx.stroke();
            });
            // arm to handlebar
            ctx.strokeStyle = rimCol; ctx.lineWidth = 2.2;
            ctx.beginPath(); ctx.moveTo(shX, shY); ctx.lineTo(cxr + 6, cyr - 20); ctx.stroke();
            // head + helmet
            ctx.fillStyle = '#f0d9c0';
            ctx.beginPath(); ctx.arc(shX + 4, shY - 5, 3.6, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = rimCol;
            ctx.beginPath(); ctx.arc(shX + 4, shY - 6, 4.2, Math.PI, Math.PI * 2); ctx.fill();

            // dust puff at contact when moving
            if (vv > 0.2) {
                ctx.fillStyle = `rgba(120,110,95,${Math.min(0.22, vv * 0.03)})`;
                for (let i = 0; i < 3; i++) {
                    const p = (amb * 2 + i / 3) % 1;
                    ctx.beginPath(); ctx.arc(cxr - 16 - p * 14, cyr - 4 - p * 6, 2 + p * 3, 0, Math.PI * 2); ctx.fill();
                }
            }
            ctx.restore();

            // live state pill (over the scene)
            ctx.font = '700 10.5px Inter, sans-serif'; ctx.textAlign = 'left';
            const pill = stt.txt.toUpperCase();
            const pw = ctx.measureText(pill).width + 18;
            ctx.fillStyle = 'rgba(10,16,26,.55)';
            ctx.beginPath(); ctx.roundRect(12, 12, pw, 20, 10); ctx.fill();
            ctx.fillStyle = stt.col; ctx.beginPath(); ctx.arc(24, 22, 3, 0, Math.PI * 2); ctx.fill();
            ctx.fillText(pill, 32, 25);

            // ── TWIN GRAPHS (pedagogical core) ──
            const gPad = 22, gTop = sceneH + 24, gH = h - gTop - 28, gW = (w - 3 * gPad) / 2;
            const graphs = [
                { x: gPad, label: 'distance / m', max: S, col: acc, val: (q) => q.s, cur: ss },
                { x: 2 * gPad + gW, label: 'speed / m/s', max: vMax * 1.15, col: cyan, val: (q) => q.v, cur: vv },
            ];
            for (const g of graphs) {
                ctx.fillStyle = 'rgba(160,200,175,.03)';
                ctx.beginPath(); ctx.roundRect(g.x - 6, gTop - 6, gW + 12, gH + 30, 8); ctx.fill();
                ctx.strokeStyle = line; ctx.lineWidth = 1;
                for (let i = 1; i < 4; i++) { const gy = gTop + (gH * i) / 4; ctx.beginPath(); ctx.moveTo(g.x, gy); ctx.lineTo(g.x + gW, gy); ctx.stroke(); }
                ctx.strokeStyle = ink; ctx.lineWidth = 1.4;
                ctx.beginPath(); ctx.moveTo(g.x, gTop); ctx.lineTo(g.x, gTop + gH); ctx.lineTo(g.x + gW, gTop + gH); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText(g.label + ' ↑', g.x + 4, gTop + 11);
                ctx.textAlign = 'right'; ctx.fillText('time / s →', g.x + gW - 2, gTop + gH + 16);
                const X = (q) => g.x + (q / T) * gW;
                const Y = (q) => gTop + gH - (q / (g.max || 1)) * gH;
                ctx.setLineDash([3, 4]); ctx.strokeStyle = line; ctx.lineWidth = 1.4; ctx.beginPath();
                for (let i = 0; i <= 120; i++) { const q = at((T * i) / 120); const px = X((T * i) / 120), py = Y(g.val(q)); i ? ctx.lineTo(px, py) : ctx.moveTo(px, py); }
                ctx.stroke(); ctx.setLineDash([]);
                ctx.strokeStyle = g.col; ctx.lineWidth = 2.6; ctx.beginPath();
                const steps = Math.max(2, Math.floor(120 * (tt / T)));
                for (let i = 0; i <= steps; i++) { const q = at((tt * i) / steps); const px = X((tt * i) / steps), py = Y(g.val(q)); i ? ctx.lineTo(px, py) : ctx.moveTo(px, py); }
                ctx.stroke();
                ctx.strokeStyle = 'rgba(251,191,36,.55)'; ctx.lineWidth = 1.2; ctx.setLineDash([3, 3]);
                ctx.beginPath(); ctx.moveTo(X(tt), gTop); ctx.lineTo(X(tt), gTop + gH); ctx.stroke(); ctx.setLineDash([]);
                ctx.fillStyle = amber; ctx.strokeStyle = '#fff'; ctx.lineWidth = 1.6;
                ctx.beginPath(); ctx.arc(X(tt), Y(g.cur), 5, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
            }

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 11' }}>
                    <span className="cw-badge">One journey · three views</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="motion right now" value={state.txt} unit="" tone="acc"
                      sub={<>t = <b>{t.toFixed(1)} s</b> · distance <b>{s.toFixed(0)} m</b> · speed <b>{v.toFixed(1)} m/s</b></>} />
                <div className="cw-btnrow">
                    {Object.entries(SCENARIOS).map(([k, sc]) => (
                        <button key={k} className={'cw-btn ' + (k === scenario ? 'cw-btn-save' : 'cw-btn-ghost')}
                                onClick={() => { setScenario(k); setT(0); setPlaying(false); }}>
                            {sc.label}
                        </button>
                    ))}
                </div>
                <div className="cw-btnrow">
                    <Button variant="save" onClick={() => { if (t >= prof.T) setT(0); setPlaying(!playing); }}>
                        {playing ? '⏸ Pause' : t >= prof.T ? '↺ Replay' : '▶ Ride'}
                    </Button>
                    <Button onClick={() => { setPlaying(false); setT(0); }}>Reset</Button>
                </div>
                <Slider label="scrub time" value={+t.toFixed(2)} min={0} max={+prof.T.toFixed(2)} step={0.05}
                        onChange={(val) => { setPlaying(false); setT(+val); }} format={(val) => `${(+val).toFixed(1)} s`} />
                <Flag kind="neutral">{SCENARIOS[scenario].blurb} The roadside markers stream past at the cyclist's real speed; beneath, the dashed curves show where each graph is heading, the bold lines are the story so far, and the amber dots mark <b>the same instant</b> on both.</Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, scenario, t })}>📌 Save this journey to my notes</button>
                )}
            </div>
        </div>
    );
}
