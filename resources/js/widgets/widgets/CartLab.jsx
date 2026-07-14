import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag, Button } from '../primitives.jsx';

// ── Widget: cart_lab ─────────────────────────────────────────────────────────
// Bespoke F = ma hero simulator (5054 · 1.5.1 LO 6). A trolley on a track:
// set the applied force and the mass, optionally switch friction on, and
// launch. The RESULTANT force — not the applied force — sets the acceleration,
// shown three ways at once: the cart pulling away, the speedometer climbing,
// and a live speed–time graph whose gradient IS the acceleration. Scenario
// values are illustrative.
//
// config: { force, mass, friction } · Notes contract: getState/setState carry
// {force, mass, friction, running}.

const FRICTION_N = 4;     // constant resistive force when enabled and moving
const T_MAX = 6;          // s shown on the graph
const V_MAX = 30;         // m/s axis

export default function CartLab({ config = {}, onReady, onAddToNote }) {
    const [force, setForce] = useState(typeof config.force === 'number' ? config.force : 8);
    const [mass, setMass] = useState(typeof config.mass === 'number' ? config.mass : 2);
    const [friction, setFriction] = useState(!!config.friction);
    const [running, setRunning] = useState(false);
    const [t, setT] = useState(0);
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { force, mass, friction, running, t };

    const fNet = Math.max(0, force - (friction ? FRICTION_N : 0));
    const stuck = friction && force <= FRICTION_N;          // never overcomes friction
    const a = stuck ? 0 : fNet / mass;

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, force: st.current.force, mass: st.current.mass, friction: st.current.friction, running: st.current.running }),
            setState: (s) => {
                if (typeof s?.force === 'number') setForce(Math.max(0, Math.min(20, s.force)));
                if (typeof s?.mass === 'number') setMass(Math.max(0.5, Math.min(4, s.mass)));
                if (typeof s?.friction === 'boolean') setFriction(s.friction);
                setRunning(false); setT(0);
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
            const S = st.current;
            const fr = S.friction ? FRICTION_N : 0;
            const isStuck = S.friction && S.force <= FRICTION_N;
            const aa = isStuck ? 0 : Math.max(0, S.force - fr) / S.mass;

            if (S.running && S.t < T_MAX) { S.t = Math.min(T_MAX, S.t + dt); setT(S.t); }
            const v = aa * S.t;

            // ── Track scene (top ~55%) ──
            const trackY = h * 0.42;
            ctx.strokeStyle = ink; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(14, trackY); ctx.lineTo(w - 14, trackY); ctx.stroke();
            // distance ticks scroll past to show speed
            const worldX = 0.5 * aa * S.t * S.t;                 // metres travelled
            const pxPerM = 14;
            ctx.strokeStyle = line; ctx.font = '600 8px "JetBrains Mono", monospace'; ctx.fillStyle = faint; ctx.textAlign = 'center';
            for (let m = Math.floor(worldX / 5) * 5; m < worldX + w / pxPerM; m += 5) {
                const px = 90 + (m - worldX) * pxPerM;
                if (px > 14 && px < w - 14) {
                    ctx.beginPath(); ctx.moveTo(px, trackY); ctx.lineTo(px, trackY + 7); ctx.stroke();
                    ctx.fillText(`${m} m`, px, trackY + 18);
                }
            }
            // cart (size ∝ mass), stays near x=90 while the world scrolls
            const cw2 = 40 + S.mass * 10, chh = 24 + S.mass * 5;
            const cartX = 90, cartY = trackY - 8;
            ctx.fillStyle = 'rgba(56,189,248,.16)'; ctx.strokeStyle = cyan; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.roundRect(cartX - cw2 / 2, cartY - chh, cw2, chh, 6); ctx.fill(); ctx.stroke();
            [-1, 1].forEach((s2) => {
                ctx.fillStyle = ink;
                ctx.beginPath(); ctx.arc(cartX + s2 * cw2 * 0.3, cartY, 7, 0, Math.PI * 2); ctx.fill();
                // wheel spokes rotate with distance
                const ang = (worldX * pxPerM) / 7;
                ctx.strokeStyle = 'rgba(0,0,0,.5)';
                ctx.beginPath(); ctx.moveTo(cartX + s2 * cw2 * 0.3 - Math.cos(ang) * 5, cartY - Math.sin(ang) * 5);
                ctx.lineTo(cartX + s2 * cw2 * 0.3 + Math.cos(ang) * 5, cartY + Math.sin(ang) * 5); ctx.stroke();
            });
            ctx.fillStyle = faint; ctx.font = '600 10px Inter, sans-serif';
            ctx.fillText(`${S.mass.toFixed(1)} kg`, cartX, cartY - chh - 8);
            // applied force arrow (right) + friction arrow (left)
            const arrow = (x0, y0, len, col, label, up) => {
                if (Math.abs(len) < 2) return;
                ctx.strokeStyle = col; ctx.fillStyle = col; ctx.lineWidth = 2.5;
                ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x0 + len, y0); ctx.stroke();
                const s3 = Math.sign(len);
                ctx.beginPath(); ctx.moveTo(x0 + len + s3 * 8, y0);
                ctx.lineTo(x0 + len, y0 - 4.5); ctx.lineTo(x0 + len, y0 + 4.5); ctx.fill();
                ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(label, x0 + len / 2, y0 + (up ? -8 : 16));
            };
            arrow(cartX + cw2 / 2, cartY - chh / 2, S.force * 6, acc, `applied ${S.force.toFixed(0)} N`, true);
            if (fr > 0 && (v > 0 || !isStuck)) arrow(cartX - cw2 / 2, cartY - chh / 2, -fr * 6, amber, `friction ${fr} N`, true);
            if (isStuck) {
                ctx.fillStyle = amber; ctx.font = '600 10.5px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText('not enough to overcome friction — resultant 0, cart stays put', 20, trackY - chh - 30);
            }

            // ── Speed-time graph (bottom) ──
            const gx = w * 0.12, gy = h * 0.56, gw = w * 0.62, gh = h * 0.36;
            const X = (tt) => gx + (tt / T_MAX) * gw;
            const Y = (vv) => gy + gh - (Math.min(vv, V_MAX) / V_MAX) * gh;
            ctx.strokeStyle = line; ctx.lineWidth = 1;
            for (let vv = 0; vv <= V_MAX; vv += 10) { ctx.beginPath(); ctx.moveTo(gx, Y(vv)); ctx.lineTo(gx + gw, Y(vv)); ctx.stroke(); }
            ctx.strokeStyle = ink; ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.moveTo(gx, gy); ctx.lineTo(gx, gy + gh); ctx.lineTo(gx + gw, gy + gh); ctx.stroke();
            ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText('time / s →', gx + gw / 2, gy + gh + 14);
            ctx.save(); ctx.translate(gx - 24, gy + gh / 2); ctx.rotate(-Math.PI / 2);
            ctx.fillText('speed / m/s →', 0, 0); ctx.restore();
            ctx.font = '600 8px "JetBrains Mono", monospace'; ctx.textAlign = 'right';
            for (let vv = 0; vv <= V_MAX; vv += 10) ctx.fillText(String(vv), gx - 4, Y(vv) + 2.5);
            // line so far — gradient IS a
            ctx.strokeStyle = acc; ctx.lineWidth = 2.5;
            ctx.beginPath(); ctx.moveTo(X(0), Y(0)); ctx.lineTo(X(S.t), Y(aa * S.t)); ctx.stroke();
            ctx.fillStyle = amber;
            ctx.beginPath(); ctx.arc(X(S.t), Y(aa * S.t), 4, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = faint; ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'left';
            ctx.fillText(`gradient = acceleration = ${aa.toFixed(1)} m/s²`, gx + 10, gy + 12);

            // live equation panel (right of the graph)
            const px2 = w * 0.87;
            ctx.font = '700 11.5px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            ctx.fillStyle = faint; ctx.fillText('resultant = ', px2, gy + 16);
            ctx.fillStyle = acc; ctx.fillText(`${S.force.toFixed(0)} − ${fr} = ${Math.max(0, S.force - fr).toFixed(0)} N`, px2, gy + 32);
            ctx.fillStyle = faint; ctx.fillText('a = F / m', px2, gy + 58);
            ctx.fillStyle = amber;
            ctx.fillText(isStuck ? 'a = 0' : `= ${Math.max(0, S.force - fr).toFixed(0)} / ${S.mass.toFixed(1)}`, px2, gy + 74);
            if (!isStuck) { ctx.fillStyle = amber; ctx.fillText(`= ${aa.toFixed(1)} m/s²`, px2, gy + 90); }

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const reset = () => { setRunning(false); setT(0); st.current.t = 0; };

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Resultant force sets the acceleration</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="acceleration (from the resultant, not the applied force)"
                      value={stuck ? '0' : (fNet / mass).toFixed(1)} unit="m/s²" tone={stuck ? 'warn' : 'acc'}
                      sub={stuck
                          ? <>the {force.toFixed(0)} N pull never beats {FRICTION_N} N friction — resultant 0, no acceleration</>
                          : <>speed now: <b>{(a * t).toFixed(1)} m/s</b> at t = {t.toFixed(1)} s — same a every second</>} />
                <Slider label="applied force" value={force} min={0} max={20} step={1} onChange={(x) => { setForce(x); reset(); }} format={(x) => `${x.toFixed(0)} N`} />
                <Slider label="mass of the trolley" value={mass} min={0.5} max={4} step={0.5} onChange={(x) => { setMass(x); reset(); }} format={(x) => `${x.toFixed(1)} kg`} />
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (friction ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => { setFriction(!friction); reset(); }}>
                        {friction ? '✓' : '+'} friction ({FRICTION_N} N)
                    </button>
                    <Button variant="save" onClick={() => setRunning(!running)}>{running ? '⏸ Pause' : '▶ Launch'}</Button>
                    <Button variant="ghost" onClick={reset}>↺ Reset</Button>
                </div>
                <Flag kind="neutral">
                    Double the mass at the same force and watch the graph's gradient halve; switch friction on and see the
                    <b> resultant</b> — not the applied force — take over: <b>a = F / m</b> only works when F is the resultant.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, force, mass, friction })}>📌 Save this run to my notes</button>
                )}
            </div>
        </div>
    );
}
