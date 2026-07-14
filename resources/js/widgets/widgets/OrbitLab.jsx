import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag, Button } from '../primitives.jsx';

// ── Widget: orbit_lab ────────────────────────────────────────────────────────
// Bespoke Circular Motion hero simulator (5054 · 1.5.4). Strictly QUALITATIVE:
// a ball whirled on a string, with a tangent velocity arrow and an inward force
// arrow always at right angles to it. Sliders change speed, radius and mass and
// a qualitative "string tension needed" bar responds; CUT the string and the
// ball leaves along the tangent — straight line, constant speed, no spiral.
//
// SYLLABUS EXCLUSION (1.5.4 #1): F = mv²/r is NOT required — this widget never
// displays that equation or any numeric force value; the tension bar is an
// unnumbered qualitative gauge only.
//
// config: { speed, radius, mass } · Notes contract: getState/setState carry
// {speed, radius, mass, cut}.

const T_GAUGE_MAX = 3 * 10 * 10 / 0.6;   // internal normaliser for the bar only (never shown)

export default function OrbitLab({ config = {}, onReady, onAddToNote }) {
    const [speed, setSpeed] = useState(typeof config.speed === 'number' ? config.speed : 6);
    const [radius, setRadius] = useState(typeof config.radius === 'number' ? config.radius : 1.2);
    const [mass, setMass] = useState(typeof config.mass === 'number' ? config.mass : 1);
    const [cut, setCut] = useState(false);
    const cvRef = useRef(null);
    const simRef = useRef({ th: -Math.PI / 2, free: null });   // free = {x, y, vx, vy} after the cut
    const st = useRef({}); st.current = { speed, radius, mass, cut };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, speed: st.current.speed, radius: st.current.radius, mass: st.current.mass, cut: st.current.cut }),
            setState: (s) => {
                if (typeof s?.speed === 'number') setSpeed(Math.max(2, Math.min(10, s.speed)));
                if (typeof s?.radius === 'number') setRadius(Math.max(0.6, Math.min(2, s.radius)));
                if (typeof s?.mass === 'number') setMass(Math.max(0.5, Math.min(3, s.mass)));
                if (typeof s?.cut === 'boolean') { setCut(s.cut); if (!s.cut) simRef.current.free = null; }
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
            const { speed: v, radius: r, mass: m, cut: isCut } = st.current;
            const sim = simRef.current;

            const cx = w * 0.42, cy = h * 0.5;
            const pxPerM = Math.min(w * 0.36, h * 0.42) / 2;   // world scale
            const R = r * pxPerM;

            // advance
            if (!isCut) {
                sim.th += (v / r) * dt * 0.55;                 // visual pacing
                sim.free = null;
            } else if (!sim.free) {
                const bx = cx + Math.cos(sim.th) * R, by = cy + Math.sin(sim.th) * R;
                sim.free = { x: bx, y: by, vx: -Math.sin(sim.th) * v, vy: Math.cos(sim.th) * v };
            } else {
                sim.free.x += sim.free.vx * pxPerM * dt * 0.55 / 1;
                sim.free.y += sim.free.vy * pxPerM * dt * 0.55 / 1;
            }

            // path circle + centre post
            ctx.setLineDash([4, 5]); ctx.strokeStyle = line; ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.arc(cx, cy, R, 0, Math.PI * 2); ctx.stroke(); ctx.setLineDash([]);
            ctx.fillStyle = ink;
            ctx.beginPath(); ctx.arc(cx, cy, 5, 0, Math.PI * 2); ctx.fill();

            const bx = sim.free ? sim.free.x : cx + Math.cos(sim.th) * R;
            const by = sim.free ? sim.free.y : cy + Math.sin(sim.th) * R;
            const offscreen = bx < -40 || bx > w + 40 || by < -40 || by > h + 40;

            // string
            if (!isCut) {
                ctx.strokeStyle = ink; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(bx, by); ctx.stroke();
            } else {
                // limp cut string at the post
                ctx.strokeStyle = faint; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.moveTo(cx, cy);
                ctx.quadraticCurveTo(cx + 14, cy + 18, cx + 6, cy + 34); ctx.stroke();
            }

            const arrow = (x0, y0, dx, dy, col, label, labelSide = 1) => {
                const len = Math.hypot(dx, dy); if (len < 1) return;
                const ux = dx / len, uy = dy / len;
                ctx.strokeStyle = col; ctx.fillStyle = col; ctx.lineWidth = 2.5;
                ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x0 + dx, y0 + dy); ctx.stroke();
                ctx.beginPath();
                ctx.moveTo(x0 + dx, y0 + dy);
                ctx.lineTo(x0 + dx - 9 * ux + 4.5 * uy, y0 + dy - 9 * uy - 4.5 * ux);
                ctx.lineTo(x0 + dx - 9 * ux - 4.5 * uy, y0 + dy - 9 * uy + 4.5 * ux);
                ctx.fill();
                ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(label, x0 + dx + uy * 14 * labelSide, y0 + dy - ux * 14 * labelSide + 3);
            };

            if (!offscreen) {
                // ball (size ∝ mass)
                ctx.fillStyle = amber;
                ctx.beginPath(); ctx.arc(bx, by, 7 + m * 3, 0, Math.PI * 2); ctx.fill();
                // velocity arrow: tangent, length ∝ speed
                const tx = sim.free ? sim.free.vx : -Math.sin(sim.th) * v;
                const ty = sim.free ? sim.free.vy : Math.cos(sim.th) * v;
                const tl = Math.hypot(tx, ty);
                arrow(bx, by, (tx / tl) * (18 + v * 4.5), (ty / tl) * (18 + v * 4.5), cyan, 'velocity');
                // inward force arrow only while the string holds
                if (!isCut) {
                    const fx = cx - bx, fy = cy - by, fl = Math.hypot(fx, fy);
                    const need = (m * v * v / r) / T_GAUGE_MAX;   // internal only
                    arrow(bx, by, (fx / fl) * (16 + need * 46), (fy / fl) * (16 + need * 46), acc, 'force toward centre', -1);
                }
            } else {
                ctx.fillStyle = faint; ctx.font = '600 11px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('gone — straight along the tangent, at steady speed', cx, cy - R - 14);
            }

            // qualitative tension gauge (right edge) — no numbers, no equation
            const gX = w - 46, gTop = 26, gH = h - 70;
            ctx.strokeStyle = line; ctx.lineWidth = 1;
            ctx.strokeRect(gX, gTop, 14, gH);
            const need = isCut ? 0 : Math.min(1, (m * v * v / r) / T_GAUGE_MAX);
            const bh = gH * need;
            const grad = ctx.createLinearGradient(0, gTop + gH - bh, 0, gTop + gH);
            grad.addColorStop(0, 'rgba(52,211,153,.9)'); grad.addColorStop(1, 'rgba(52,211,153,.35)');
            ctx.fillStyle = grad;
            ctx.fillRect(gX + 1, gTop + gH - bh, 12, bh);
            ctx.fillStyle = faint; ctx.font = '600 8.5px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.save(); ctx.translate(gX - 8, gTop + gH / 2); ctx.rotate(-Math.PI / 2);
            ctx.fillText('string tension needed', 0, 0); ctx.restore();
            ctx.fillText('high', gX + 7, gTop - 8);
            ctx.fillText('low', gX + 7, gTop + gH + 14);

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Perpendicular force · circular path</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="motion right now"
                      value={cut ? 'no inward force' : 'circling'}
                      tone={cut ? 'warn' : 'acc'}
                      sub={cut
                          ? <>the ball leaves along the <b>tangent</b> — a straight line at steady speed, not an outward spiral</>
                          : <>speed steady, <b>direction changing</b> — so the velocity is changing, and a resultant force acts, always at right angles to the motion</>} />
                <Slider label="speed" value={speed} min={2} max={10} step={0.5} onChange={(x) => setSpeed(x)} format={(x) => `${x.toFixed(1)} m/s`} />
                <Slider label="radius of the circle" value={radius} min={0.6} max={2} step={0.1} onChange={(x) => setRadius(x)} format={(x) => `${x.toFixed(1)} m`} />
                <Slider label="mass of the ball" value={mass} min={0.5} max={3} step={0.25} onChange={(x) => setMass(x)} format={(x) => `${x.toFixed(2)} kg`} />
                <div className="cw-btnrow">
                    <Button variant={cut ? 'ghost' : 'save'} onClick={() => setCut(true)}>✂ Cut the string</Button>
                    <Button variant="ghost" onClick={() => { setCut(false); simRef.current.free = null; }}>↺ Re-tie</Button>
                </div>
                <Flag kind="neutral">
                    Watch the two arrows: the inward pull stays <b>perpendicular</b> to the velocity, so it changes the
                    velocity's <b>direction</b> but never its size. Faster ball, tighter circle or heavier ball — the
                    tension bar shows more force is needed (how much more is beyond this course).
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, speed, radius, mass, cut })}>📌 Save this orbit to my notes</button>
                )}
            </div>
        </div>
    );
}
