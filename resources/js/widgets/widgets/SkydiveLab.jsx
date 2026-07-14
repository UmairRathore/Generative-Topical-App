import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag, Button } from '../primitives.jsx';

// ── Widget: skydive_lab ──────────────────────────────────────────────────────
// Bespoke Friction/Drag hero simulator (5054 · 1.5.2). A skydiver falls through
// an animated sky: her weight arrow is constant, the drag arrow GROWS with
// speed, and a live speed–time graph draws itself alongside. When the arrows
// match, the state pill announces terminal velocity — and the parachute button
// restarts the whole story with a bigger drag and a lower balance point.
// The four-link exam chain (weight constant → drag grows with speed → resultant
// shrinks → drag equals weight, constant velocity) is played, not recited.
//
// All numbers are scenario data (an 800 N jumper); no canonical constants are
// asserted and no equations are displayed.
//
// config: { autoJump } · Notes contract: getState/setState carry {t, chute} snapshots.

const W_N = 800;                    // jumper's weight (scenario data)
const MASS = 80;                    // internal sim mass for the integrator
const K_FREE = 0.32;                // drag = k·v² → freefall terminal ≈ 50 m/s
const K_CHUTE = 12.8;               // parachute terminal ≈ 7.9 m/s
const T_MAX = 36;

export default function SkydiveLab({ config = {}, onReady, onAddToNote }) {
    const cvRef = useRef(null);
    const [playing, setPlaying] = useState(!!config.autoJump);
    const [chuteAt, setChuteAt] = useState(null);     // time the parachute opened
    const [t, setT] = useState(0);
    const histRef = useRef([{ t: 0, v: 0 }]);          // integrated speed history
    const st = useRef({}); st.current = { t, playing, chuteAt };

    // Deterministic re-integration up to time tt (so scrub/replay/setState work).
    const integrate = (tt, chute) => {
        const dt = 0.02; let v = 0; const hist = [{ t: 0, v: 0 }];
        for (let x = dt; x <= tt + 1e-9; x += dt) {
            const k = chute != null && x >= chute ? K_CHUTE : K_FREE;
            const drag = k * v * v;
            v = Math.max(0, v + ((W_N - drag) / MASS) * dt);
            hist.push({ t: x, v });
        }
        return hist;
    };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, t: st.current.t, chute: st.current.chuteAt }),
            setState: (s) => {
                if (!s) return;
                const chute = typeof s.chute === 'number' ? s.chute : null;
                setChuteAt(chute);
                const tt = typeof s.t === 'number' ? Math.min(T_MAX, s.t) : 0;
                histRef.current = integrate(tt, chute);
                setT(tt); setPlaying(false);
            },
        });
    }, [onReady]); // eslint-disable-line

    // Play loop (append to the history incrementally).
    useEffect(() => {
        if (!playing) return undefined;
        let raf, last = performance.now();
        const tick = (now) => {
            const dt = Math.min(0.05, (now - last) / 1000); last = now;
            setT((prev) => {
                const next = Math.min(T_MAX, prev + dt);
                const hist = histRef.current;
                let hv = hist[hist.length - 1];
                for (let x = hv.t + 0.02; x <= next; x += 0.02) {
                    const k = st.current.chuteAt != null && x >= st.current.chuteAt ? K_CHUTE : K_FREE;
                    const v2 = Math.max(0, hv.v + ((W_N - k * hv.v * hv.v) / MASS) * 0.02);
                    hv = { t: x, v: v2 }; hist.push(hv);
                }
                if (next >= T_MAX) setPlaying(false);
                return next;
            });
            raf = requestAnimationFrame(tick);
        };
        raf = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(raf);
    }, [playing]);

    const hist = histRef.current;
    const cur = hist[Math.min(hist.length - 1, Math.max(0, Math.round(t / 0.02)))] || hist[hist.length - 1];
    const v = cur.v;
    const k = chuteAt != null && t >= chuteAt ? K_CHUTE : K_FREE;
    const drag = k * v * v;
    const resultant = W_N - drag;
    const balanced = Math.abs(resultant) < W_N * 0.02 && v > 0.5;
    const state = t === 0 ? { txt: 'ready to jump', col: '#9ca3af' }
        : balanced ? { txt: 'terminal velocity — drag equals weight', col: '#34D399' }
        : resultant > 0 ? { txt: 'speeding up — drag still smaller than weight', col: '#38BDF8' }
        : { txt: 'slowing — parachute drag exceeds weight', col: '#FBBF24' };

    // ── Draw: sky scene + live speed–time graph ───────────────────────────────
    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        const draw = () => {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return;
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
            const cyan = '#38BDF8', amber = '#FBBF24', red = '#FB7185';
            const { t: tt, chuteAt: ca } = st.current;
            const histN = histRef.current;
            const curN = histN[Math.min(histN.length - 1, Math.max(0, Math.round(tt / 0.02)))] || histN[0];
            const vv = curN.v;
            const kk = ca != null && tt >= ca ? K_CHUTE : K_FREE;
            const dg = kk * vv * vv, res = W_N - dg;

            // ── Sky panel (left 44%) ──
            const sky = { x: 14, y: 14, w: w * 0.44 - 20, h: h - 28 };
            const g1 = ctx.createLinearGradient(0, sky.y, 0, sky.y + sky.h);
            g1.addColorStop(0, 'rgba(56,189,248,.16)'); g1.addColorStop(1, 'rgba(56,189,248,.03)');
            ctx.fillStyle = g1; ctx.beginPath(); ctx.roundRect(sky.x, sky.y, sky.w, sky.h, 12); ctx.fill();
            ctx.save(); ctx.beginPath(); ctx.roundRect(sky.x, sky.y, sky.w, sky.h, 12); ctx.clip();
            // clouds streaming upward: apparent motion of the fall
            let fallen = 0; for (let i = 1; i < histN.length && histN[i].t <= tt; i++) fallen += histN[i].v * 0.02;
            ctx.fillStyle = 'rgba(255,255,255,.10)';
            for (let i = 0; i < 9; i++) {
                const cy = ((i * 293 - fallen * 6) % (sky.h + 80) + sky.h + 80) % (sky.h + 80) + sky.y - 40;
                const cx2 = sky.x + 24 + ((i * 137) % (sky.w - 60));
                ctx.beginPath(); ctx.ellipse(cx2, cy, 26, 9, 0, 0, Math.PI * 2);
                ctx.ellipse(cx2 + 18, cy + 4, 18, 7, 0, 0, Math.PI * 2); ctx.fill();
            }
            // skydiver at fixed height; camera falls with her
            const px = sky.x + sky.w / 2, py = sky.y + sky.h * 0.42;
            if (ca != null && tt >= ca) { // canopy
                ctx.strokeStyle = amber; ctx.lineWidth = 2; ctx.fillStyle = 'rgba(251,191,36,.22)';
                ctx.beginPath(); ctx.arc(px, py - 46, 30, Math.PI, 0); ctx.closePath(); ctx.fill(); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(px - 28, py - 44); ctx.lineTo(px - 5, py - 12);
                ctx.moveTo(px + 28, py - 44); ctx.lineTo(px + 5, py - 12); ctx.stroke();
            }
            ctx.strokeStyle = ink; ctx.lineWidth = 2.4; ctx.fillStyle = ink;
            ctx.beginPath(); ctx.arc(px, py - 16, 5, 0, Math.PI * 2); ctx.fill();          // head
            ctx.beginPath(); ctx.moveTo(px, py - 11); ctx.lineTo(px, py + 6); ctx.stroke(); // torso
            ctx.beginPath(); ctx.moveTo(px, py - 6); ctx.lineTo(px - 10, py - 1); ctx.moveTo(px, py - 6); ctx.lineTo(px + 10, py - 1); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(px, py + 6); ctx.lineTo(px - 7, py + 17); ctx.moveTo(px, py + 6); ctx.lineTo(px + 7, py + 17); ctx.stroke();
            // force arrows: weight constant, drag grows
            const arrow = (y0, y1, col, label, val) => {
                ctx.strokeStyle = col; ctx.fillStyle = col; ctx.lineWidth = 3;
                ctx.beginPath(); ctx.moveTo(px, y0); ctx.lineTo(px, y1); ctx.stroke();
                const dir = Math.sign(y1 - y0);
                ctx.beginPath(); ctx.moveTo(px, y1); ctx.lineTo(px - 5, y1 - 7 * dir); ctx.lineTo(px + 5, y1 - 7 * dir); ctx.closePath(); ctx.fill();
                ctx.font = '700 10px "JetBrains Mono", monospace'; ctx.textAlign = 'left';
                ctx.fillText(`${label} ${Math.round(val)} N`, px + 12, (y0 + y1) / 2 + 3);
            };
            const scaleF = 52 / W_N;
            arrow(py + 20, py + 20 + W_N * scaleF, red, 'weight', W_N);
            if (dg > 4) arrow(py - 24, py - 24 - dg * scaleF, cyan, 'drag', dg);
            ctx.restore();

            // ── Speed–time graph (right) ──
            const gx = sky.x + sky.w + 26, gw = w - gx - 20, gy = 30, gh = h - 76;
            ctx.strokeStyle = line; ctx.lineWidth = 1;
            for (let i = 1; i < 4; i++) { const yy = gy + (gh * i) / 4; ctx.beginPath(); ctx.moveTo(gx, yy); ctx.lineTo(gx + gw, yy); ctx.stroke(); }
            ctx.strokeStyle = ink; ctx.lineWidth = 1.4;
            ctx.beginPath(); ctx.moveTo(gx, gy); ctx.lineTo(gx, gy + gh); ctx.lineTo(gx + gw, gy + gh); ctx.stroke();
            ctx.fillStyle = faint; ctx.font = '600 10px Inter, sans-serif';
            ctx.textAlign = 'left'; ctx.fillText('speed / m/s ↑', gx + 4, gy - 8 + 4);
            ctx.textAlign = 'right'; ctx.fillText('time / s →', gx + gw - 2, gy + gh + 16);
            const vTop = 58;
            const X = (q) => gx + (q / T_MAX) * gw, Y = (q) => gy + gh - (q / vTop) * gh;
            // terminal guide(s)
            const vt1 = Math.sqrt(W_N / K_FREE);
            ctx.setLineDash([4, 4]); ctx.strokeStyle = 'rgba(52,211,153,.4)';
            ctx.beginPath(); ctx.moveTo(gx, Y(vt1)); ctx.lineTo(gx + gw, Y(vt1)); ctx.stroke(); ctx.setLineDash([]);
            ctx.fillStyle = acc; ctx.textAlign = 'left'; ctx.fillText('terminal (no chute)', gx + 6, Y(vt1) - 5);
            // live curve
            ctx.strokeStyle = cyan; ctx.lineWidth = 2.6; ctx.beginPath();
            for (let i = 0; i < histN.length && histN[i].t <= tt; i += 4) {
                const p = histN[i]; const xx = X(p.t), yy = Y(p.v);
                i ? ctx.lineTo(xx, yy) : ctx.moveTo(xx, yy);
            }
            ctx.stroke();
            if (ca != null && ca <= tt) {
                ctx.strokeStyle = amber; ctx.setLineDash([3, 3]); ctx.lineWidth = 1.2;
                ctx.beginPath(); ctx.moveTo(X(ca), gy); ctx.lineTo(X(ca), gy + gh); ctx.stroke(); ctx.setLineDash([]);
                ctx.fillStyle = amber; ctx.fillText('chute opens', X(ca) + 4, gy + 14);
            }
            ctx.fillStyle = amber; ctx.strokeStyle = '#fff'; ctx.lineWidth = 1.6;
            ctx.beginPath(); ctx.arc(X(tt), Y(vv), 5, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
            // resultant bar under graph
            const bw = gw * 0.9, bx = gx + gw * 0.05, by = gy + gh + 26;
            ctx.fillStyle = faint; ctx.textAlign = 'left'; ctx.fillText('resultant force', bx, by - 4);
            ctx.strokeStyle = line; ctx.strokeRect(bx, by, bw, 8);
            const frac = Math.max(0, Math.min(1, res / W_N));
            ctx.fillStyle = res > W_N * 0.02 ? cyan : res < -W_N * 0.02 ? amber : acc;
            ctx.fillRect(bx, by, bw * Math.abs(frac || (balancedNow(res) ? 0.02 : 0)), 8);
            function balancedNow(r) { return Math.abs(r) < W_N * 0.02; }
        };
        draw();
        const ro = new ResizeObserver(draw);
        ro.observe(host);
        return () => ro.disconnect();
    }, [t, chuteAt]);

    const reset = () => { setPlaying(false); setChuteAt(null); setT(0); histRef.current = [{ t: 0, v: 0 }]; };

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Watch drag catch the weight</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="motion right now" value={state.txt} unit="" tone="acc"
                      sub={<>t = <b>{t.toFixed(1)} s</b> · speed <b>{v.toFixed(1)} m/s</b> · weight <b>{W_N} N</b> vs drag <b>{Math.round(drag)} N</b></>} />
                <div className="cw-btnrow">
                    <Button variant="save" onClick={() => { if (t >= T_MAX) reset(); setPlaying(!playing); }}>
                        {playing ? '⏸ Pause' : t === 0 ? '▶ Jump' : t >= T_MAX ? '↺ New jump' : '▶ Resume'}
                    </Button>
                    <Button onClick={() => { if (t > 0.5 && chuteAt == null) setChuteAt(st.current.t); }}>
                        🪂 Open parachute
                    </Button>
                    <Button onClick={reset}>Reset</Button>
                </div>
                <Flag kind="neutral">
                    Weight never changes — watch the <b>drag arrow grow with speed</b> until the two match and the
                    curve flattens: terminal velocity. Open the parachute and the story replays with a bigger drag
                    and a lower, safer balance point (still falling — just no longer changing speed).
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, t, chute: chuteAt })}>📌 Save this jump to my notes</button>
                )}
            </div>
        </div>
    );
}
