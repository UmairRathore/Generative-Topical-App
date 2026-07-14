import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag } from '../primitives.jsx';

// ── Widget: convection_lab ───────────────────────────────────────────────────
// Bespoke Convection hero simulator (5054 · 2.3.2). A heated fluid circulates
// in a visible loop: warmed fluid becomes LESS DENSE and rises, cools at the
// top, becomes denser and sinks — carrying energy around with it. Two modes,
// each a classic experiment: LIQUID (a beaker over a bunsen, potassium
// permanganate dye tracing the current) and GAS (a warm room, smoke tracing
// the air current). Flow particles are tinted by temperature so the density
// story is read directly. Illustrative scenario.
//
// Lake-bar immersion (physical fluid scene). config: { mode } · Notes contract:
// getState/setState carry {mode}.

const N = 84;
const rand = () => Math.random();

export default function ConvectionLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(config.mode === 'gas' ? 'gas' : 'liquid');
    const cvRef = useRef(null);
    const pRef = useRef(null);
    const dyeRef = useRef([]);
    const st = useRef({}); st.current = { mode };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode }),
            setState: (s) => { if (s?.mode === 'liquid' || s?.mode === 'gas') setMode(s.mode); },
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
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const isGas = st.current.mode === 'gas';

            // ── vessel geometry (the circulation region) ──
            const vx0 = w * 0.16, vx1 = w * 0.72, vy0 = h * 0.14, vy1 = h * 0.80;
            const xc = (vx0 + vx1) / 2, yc = (vy0 + vy1) / 2;
            const rx = (vx1 - vx0) / 2, ry = (vy1 - vy0) / 2;
            const inEllipse = (x, y) => ((x - xc) / rx) ** 2 + ((y - yc) / ry) ** 2 < 0.92;

            // room / lab backdrop
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, isGas ? '#232a33' : '#1e242c'); bg.addColorStop(1, '#171c22');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);

            if (isGas) {
                // room: floor, back wall, a window
                ctx.fillStyle = 'rgba(210,225,240,.05)';
                ctx.beginPath(); ctx.roundRect(vx0 - 10, vy0 - 8, vx1 - vx0 + 20, vy1 - vy0 + 18, 6); ctx.fill();
                ctx.strokeStyle = '#3d4652'; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.roundRect(vx0 - 10, vy0 - 8, vx1 - vx0 + 20, vy1 - vy0 + 18, 6); ctx.stroke();
                ctx.fillStyle = 'rgba(120,160,210,.12)';
                ctx.fillRect(vx1 - 60, vy0 + 8, 44, 40);
                ctx.strokeStyle = '#3d4652'; ctx.strokeRect(vx1 - 60, vy0 + 8, 44, 40);
            } else {
                // beaker of water
                ctx.fillStyle = 'rgba(56,160,210,.16)';
                ctx.fillRect(vx0, vy0 + 6, vx1 - vx0, vy1 - vy0);
                const glass = ctx.createLinearGradient(vx0, 0, vx1, 0);
                glass.addColorStop(0, 'rgba(255,255,255,.22)'); glass.addColorStop(0.12, 'rgba(255,255,255,.04)');
                glass.addColorStop(0.88, 'rgba(255,255,255,.04)'); glass.addColorStop(1, 'rgba(255,255,255,.14)');
                ctx.strokeStyle = glass; ctx.lineWidth = 3;
                ctx.beginPath(); ctx.moveTo(vx0, vy0); ctx.lineTo(vx0, vy1); ctx.lineTo(vx1, vy1); ctx.lineTo(vx1, vy0); ctx.stroke();
                ctx.strokeStyle = 'rgba(190,235,255,.5)'; ctx.lineWidth = 1.4;
                ctx.beginPath(); ctx.moveTo(vx0, vy0 + 6); ctx.lineTo(vx1, vy0 + 6); ctx.stroke();   // water surface
                // tripod + gauze
                ctx.strokeStyle = '#6b7280'; ctx.lineWidth = 2.5;
                ctx.beginPath(); ctx.moveTo(vx0 - 6, vy1); ctx.lineTo(vx0 - 18, h - 6); ctx.moveTo(vx1 + 6, vy1); ctx.lineTo(vx1 + 18, h - 6); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(vx0 - 8, vy1 + 4); ctx.lineTo(vx1 + 8, vy1 + 4); ctx.stroke();  // gauze
            }

            // heat source (bottom-left) + flame
            const heatX = vx0 + (vx1 - vx0) * 0.24, heatY = vy1;
            for (let f = 0; f < 3; f++) {
                const fh = 16 + Math.sin(now / 90 + f) * 4;
                ctx.fillStyle = f % 2 ? 'rgba(255,180,70,.85)' : 'rgba(255,90,60,.7)';
                ctx.beginPath();
                ctx.moveTo(heatX - 7 + f * 4, heatY + 16);
                ctx.quadraticCurveTo(heatX - 3 + f * 4, heatY + 16 - fh * 0.6, heatX + f * 3, heatY + 16 - fh);
                ctx.quadraticCurveTo(heatX + 5 + f * 4, heatY + 16 - fh * 0.6, heatX + 7 + f * 4, heatY + 16);
                ctx.closePath(); ctx.fill();
            }
            ctx.fillStyle = faint; ctx.font = '600 8.5px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText(isGas ? 'heater' : 'bunsen', heatX, heatY + 30);

            // ── init / advect particles on a clockwise loop (heat on the left) ──
            if (!pRef.current) {
                pRef.current = Array.from({ length: N }, () => {
                    let x, y; do { x = vx0 + rand() * (vx1 - vx0); y = vy0 + rand() * (vy1 - vy0); } while (!inEllipse(x, y));
                    return { x, y, temp: rand() * 0.3 };
                });
            }
            const omega = 2.4;
            const speed = (isGas ? 26 : 20);
            pRef.current.forEach((p) => {
                // clockwise rotation about centre: up on the left, right on top, down on the right
                let vxp = -omega * ((p.y - yc) / ry);
                let vyp = omega * ((p.x - xc) / rx);
                p.x += vxp * speed * dt; p.y += vyp * speed * dt;
                if (!inEllipse(p.x, p.y)) {
                    // nudge back toward centre
                    p.x += (xc - p.x) * 0.05; p.y += (yc - p.y) * 0.05;
                }
                // heat zone: near the flame (lower-left of the loop)
                if (p.x < xc && p.y > yc && Math.hypot(p.x - heatX, p.y - heatY) < ry * 0.9) p.temp = 1;
                p.temp *= 0.988;
                // colour by temperature: warm (less dense, rising) → cool (denser, sinking)
                const t = Math.max(0, Math.min(1, p.temp));
                const r = Math.round(80 + t * 175), g = Math.round(150 - t * 20), b = Math.round(230 - t * 170);
                ctx.fillStyle = `rgba(${r},${g},${b},${0.35 + t * 0.4})`;
                ctx.beginPath(); ctx.arc(p.x, p.y, isGas ? 2.6 : 2.9, 0, Math.PI * 2); ctx.fill();
            });

            // dye / smoke tracer emitted at the source
            if (dyeRef.current.length < 60 && (now % 40 < 20)) {
                dyeRef.current.push({ x: heatX + (rand() - 0.5) * 8, y: heatY - 4, life: 1 });
            }
            dyeRef.current = dyeRef.current.filter((d) => d.life > 0);
            dyeRef.current.forEach((d) => {
                const vxp = -omega * ((d.y - yc) / ry), vyp = omega * ((d.x - xc) / rx);
                d.x += vxp * speed * dt; d.y += vyp * speed * dt; d.life -= dt * 0.12;
                if (!inEllipse(d.x, d.y)) { d.x += (xc - d.x) * 0.05; d.y += (yc - d.y) * 0.05; }
                ctx.fillStyle = isGas ? `rgba(210,214,220,${d.life * 0.4})` : `rgba(150,70,190,${d.life * 0.7})`;
                ctx.beginPath(); ctx.arc(d.x, d.y, isGas ? 3.4 : 2.6, 0, Math.PI * 2); ctx.fill();
            });
            // KMnO4 crystal marker (liquid)
            if (!isGas) { ctx.fillStyle = '#7a2a9a'; ctx.beginPath(); ctx.arc(heatX, heatY - 3, 3, 0, Math.PI * 2); ctx.fill(); }

            // circulation arrows + density labels
            const arrow = (x, y, dx, dy) => {
                ctx.strokeStyle = 'rgba(230,238,245,.4)'; ctx.fillStyle = 'rgba(230,238,245,.4)'; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + dx, y + dy); ctx.stroke();
                const len = Math.hypot(dx, dy), ux = dx / len, uy = dy / len;
                ctx.beginPath(); ctx.moveTo(x + dx, y + dy);
                ctx.lineTo(x + dx - 7 * ux + 4 * uy, y + dy - 7 * uy - 4 * ux);
                ctx.lineTo(x + dx - 7 * ux - 4 * uy, y + dy - 7 * uy + 4 * ux); ctx.fill();
            };
            arrow(vx0 + 8, yc + 20, 0, -34);          // up the left
            arrow(xc - 16, vy0 + 12, 34, 0);          // across the top
            arrow(vx1 - 8, yc - 20, 0, 34);           // down the right
            ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'left';
            ctx.fillStyle = '#f0a060'; ctx.fillText('warm → less dense → rises', vx0 + 14, yc + 4);
            ctx.textAlign = 'right';
            ctx.fillStyle = '#6fb0e0'; ctx.fillText('cools → denser → sinks', vx1 - 14, yc + 4);

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    // reset the tracer when switching modes so the new scene reads cleanly
    const switchMode = (m) => { setMode(m); dyeRef.current = []; };

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{mode === 'liquid' ? 'Beaker · dye traces the current' : 'Room · smoke traces the current'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="the convection current"
                      value="rise · spread · sink · return" tone="acc"
                      sub={<>heat makes the fluid <b>less dense</b> so it rises; at the top it <b>cools</b>, grows denser and sinks — a loop that carries energy right round the {mode === 'liquid' ? 'beaker' : 'room'}</>} />
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'liquid' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => switchMode('liquid')}>💧 Liquid (beaker + dye)</button>
                    <button className={'cw-btn ' + (mode === 'gas' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => switchMode('gas')}>💨 Gas (room + smoke)</button>
                </div>
                <Flag kind="neutral">
                    Convection needs a <b>fluid</b> — a liquid or a gas — because the material itself moves. Warmed fluid
                    <b> expands, becomes less dense, and floats up</b> through the denser fluid around it; cooled fluid does the
                    reverse. The {mode === 'liquid' ? 'purple dye' : 'smoke'} makes the current visible — the same experiment
                    that proves convection is happening. Solids can't do this; their particles are fixed (that's conduction).
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode })}>📌 Save this current to my notes</button>
                )}
            </div>
        </div>
    );
}
