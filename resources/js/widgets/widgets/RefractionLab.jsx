import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: refraction_lab ───────────────────────────────────────────────────
// Bespoke Refraction of Light hero (5054 · 3.2.2). Three modes: REFRACT — a
// glowing ray enters a rendered glass block, bending toward the normal, and
// leaves bending away, with live angle-of-incidence / angle-of-refraction and
// n = sin i / sin r readouts. TIR — a semicircular block; raise the angle
// inside the glass past the critical angle and the refracted ray gives way to
// total internal reflection (n = 1 / sin c). FIBRE — an optical fibre guiding
// light by repeated total internal reflection. n(glass) = 1.5 scenario.
//
// Lake-bar immersion + direction-ray convention with angle readouts.
// config: { mode, angle } · Notes: getState/setState carry {mode, angle}.

const N = 1.5;                          // glass refractive index (scenario)
const CRIT = Math.asin(1 / N) * 180 / Math.PI;   // ≈ 41.8°

export default function RefractionLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['refract', 'tir', 'fibre'].includes(config.mode) ? config.mode : 'refract');
    const [angle, setAngle] = useState(typeof config.angle === 'number' ? config.angle : 45);
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, angle };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, angle: st.current.angle }),
            setState: (s) => {
                if (['refract', 'tir', 'fibre'].includes(s?.mode)) setMode(s.mode);
                if (typeof s?.angle === 'number') setAngle(Math.max(0, Math.min(80, s.angle)));
            },
        });
    }, [onReady]); // eslint-disable-line

    // live readouts
    const iRad = angle * Math.PI / 180;
    const rRefract = Math.asin(Math.min(1, Math.sin(iRad) / N)) * 180 / Math.PI;  // air→glass
    const tir = mode === 'tir' && angle >= CRIT;
    const rExit = mode === 'tir' && !tir ? Math.asin(Math.min(1, N * Math.sin(iRad))) * 180 / Math.PI : 0;

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, t0 = performance.now();
        const draw = (now) => {
            const amb = (now - t0) / 1000;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', cyan = '#38BDF8';
            const S = st.current, a = S.angle * Math.PI / 180;
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#121820'); bg.addColorStop(1, '#0d1219');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);
            const beam = (x0, y0, x1, y1, col, wide = 1) => {
                ctx.strokeStyle = col.replace('1)', '.16)'); ctx.lineWidth = 8 * wide; ctx.lineCap = 'round';
                ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke();
                ctx.strokeStyle = col; ctx.lineWidth = 2.4 * wide;
                ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke();
                const ang = Math.atan2(y1 - y0, x1 - x0), mx = x0 + (x1 - x0) * 0.55, my = y0 + (y1 - y0) * 0.55;
                ctx.fillStyle = col;
                ctx.beginPath(); ctx.moveTo(mx + Math.cos(ang) * 8, my + Math.sin(ang) * 8);
                ctx.lineTo(mx - Math.cos(ang - 0.5) * 9, my - Math.sin(ang - 0.5) * 9);
                ctx.lineTo(mx - Math.cos(ang + 0.5) * 9, my - Math.sin(ang + 0.5) * 9); ctx.closePath(); ctx.fill();
            };
            const glassFill = (fn) => {
                const g = ctx.createLinearGradient(0, 0, w, 0);
                g.addColorStop(0, 'rgba(90,180,190,.16)'); g.addColorStop(0.5, 'rgba(120,210,220,.22)'); g.addColorStop(1, 'rgba(90,180,190,.16)');
                ctx.fillStyle = g; fn(); ctx.fill(); ctx.strokeStyle = 'rgba(180,235,240,.5)'; ctx.lineWidth = 2; fn(); ctx.stroke();
            };
            const dashN = (x0, y0, x1, y1) => { ctx.strokeStyle = 'rgba(255,255,255,.4)'; ctx.setLineDash([5, 4]); ctx.lineWidth = 1.2; ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke(); ctx.setLineDash([]); };

            if (S.mode === 'refract') {
                const bx0 = w * 0.24, bx1 = w * 0.72, ty = h * 0.32, by = h * 0.74, px = w * 0.42;
                glassFill(() => { ctx.beginPath(); ctx.rect(bx0, ty, bx1 - bx0, by - ty); });
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'right';
                ctx.fillText('glass (n = 1.5)', bx1 - 8, by - 8); ctx.textAlign = 'left'; ctx.fillText('air', bx0 + 8, ty + 14);
                const rr = Math.asin(Math.min(1, Math.sin(a) / N));
                // normal at entry
                dashN(px, ty - 70, px, ty + 60);
                // incident (air, from upper-left) → refracted (glass, toward normal) → emergent (air, away)
                const L = Math.min(w * 0.2, h * 0.32);
                beam(px - Math.sin(a) * L, ty - Math.cos(a) * L, px, ty, 'rgba(255,236,150,1)');
                const qx = px + Math.tan(rr) * (by - ty), qy = by;
                beam(px, ty, qx, qy, 'rgba(255,236,150,1)');
                dashN(qx, by - 60, qx, by + 60);
                beam(qx, qy, qx + Math.sin(a) * L, qy + Math.cos(a) * L, 'rgba(255,236,150,1)');
                // angle arcs + readouts at entry
                ctx.strokeStyle = amber; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.arc(px, ty, 34, -Math.PI / 2 - a, -Math.PI / 2); ctx.stroke();
                ctx.strokeStyle = cyan; ctx.beginPath(); ctx.arc(px, ty, 30, Math.PI / 2, Math.PI / 2 + rr); ctx.stroke();
                ctx.fillStyle = amber; ctx.font = '700 10px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(`i = ${S.angle}°`, px - 52, ty - 30);
                ctx.fillStyle = cyan; ctx.fillText(`r = ${(rr * 180 / Math.PI).toFixed(0)}°`, px + 46, ty + 40);
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif';
                ctx.fillText(`n = sin i / sin r = ${(Math.sin(a) / Math.max(1e-3, Math.sin(rr))).toFixed(2)}`, w * 0.5, h - 12);
            } else if (S.mode === 'tir') {
                const fx = w * 0.56, cy = h * 0.5, Rr = Math.min(w * 0.30, h * 0.42), isTir = S.angle >= CRIT;
                // semicircular block (flat face vertical at fx, bulge to the left)
                glassFill(() => { ctx.beginPath(); ctx.moveTo(fx, cy - Rr); ctx.arc(fx, cy, Rr, -Math.PI / 2, Math.PI / 2, true); ctx.closePath(); });
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText('glass', fx - Rr * 0.6, cy - 6); ctx.fillText('air', fx + 16, cy - Rr * 0.6);
                // normal at the flat-face centre (horizontal)
                dashN(fx - 70, cy, fx + 80, cy);
                // ray enters along a radius (no bend at curved surface) to centre C
                const ex = fx - Math.cos(a) * Rr, ey = cy + Math.sin(a) * Rr;   // point on curved edge (lower-left)
                beam(ex, ey, fx, cy, 'rgba(255,236,150,1)');
                if (!isTir) {
                    const rExitR = Math.asin(Math.min(1, N * Math.sin(a)));
                    beam(fx, cy, fx + Math.cos(rExitR) * Rr, cy - Math.sin(rExitR) * Rr, 'rgba(255,236,150,1)');   // refracted out
                    beam(fx, cy, fx - Math.cos(a) * Rr * 0.7, cy - Math.sin(a) * Rr * 0.7, 'rgba(255,236,150,1)', 0.55); // weak reflected
                } else {
                    beam(fx, cy, fx - Math.cos(a) * Rr, cy - Math.sin(a) * Rr, 'rgba(255,236,150,1)');   // fully reflected
                }
                ctx.fillStyle = isTir ? '#FB7185' : acc; ctx.font = '700 11px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(`angle in glass = ${S.angle}°   ·   critical angle c = ${CRIT.toFixed(0)}°`, w * 0.5, h - 26);
                ctx.fillStyle = isTir ? '#FB7185' : faint;
                ctx.fillText(isTir ? 'past c → TOTAL INTERNAL REFLECTION (n = 1/sin c)' : 'below c → refracts out (with a weak reflection)', w * 0.5, h - 10);
            } else {
                // optical fibre — light guided by repeated total internal reflection
                const midY = h * 0.44, coreH = 26, x0 = w * 0.08, x1 = w * 0.92;
                // gently curved fibre (core + cladding)
                const yOf = (x) => midY + Math.sin((x - x0) / (x1 - x0) * Math.PI) * h * 0.16;
                ctx.strokeStyle = 'rgba(120,210,220,.25)'; ctx.lineWidth = coreH + 14;
                ctx.beginPath(); for (let x = x0; x <= x1; x += 6) ctx.lineTo(x, yOf(x)); ctx.stroke();
                ctx.strokeStyle = 'rgba(120,210,220,.5)'; ctx.lineWidth = coreH;
                ctx.beginPath(); for (let x = x0; x <= x1; x += 6) ctx.lineTo(x, yOf(x)); ctx.stroke();
                // zig-zag ray bouncing (TIR) inside the core
                ctx.strokeStyle = 'rgba(255,236,150,.2)'; ctx.lineWidth = 6;
                ctx.strokeStyle = 'rgba(255,236,150,1)';
                const seg = 18, amp = coreH * 0.4;
                ctx.lineWidth = 2.4; ctx.beginPath();
                for (let k = 0; k <= seg; k++) {
                    const x = x0 + (x1 - x0) * k / seg;
                    const bounce = (k % 2 === 0 ? -1 : 1) * amp;
                    k === 0 ? ctx.moveTo(x, yOf(x) + bounce) : ctx.lineTo(x, yOf(x) + bounce);
                }
                ctx.stroke();
                // a moving pulse
                const pk = (amb * 0.4) % 1, pxp = x0 + (x1 - x0) * pk;
                ctx.fillStyle = '#fff2c0'; ctx.beginPath(); ctx.arc(pxp, yOf(pxp) + ((Math.floor(pk * seg) % 2 === 0 ? -1 : 1) * amp), 4, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText('core (higher n)', x0 + 10, midY - coreH); ctx.fillText('cladding (lower n)', x0 + 10, midY - coreH + 14);
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('total internal reflection at every bounce keeps the light trapped in the core', w * 0.5, h - 12);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{mode === 'refract' ? 'Glass block · n = sin i / sin r' : mode === 'tir' ? 'Critical angle · total internal reflection' : 'Optical fibre · guided light'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'refract' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('refract')}>🔷 Glass block</button>
                    <button className={'cw-btn ' + (mode === 'tir' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('tir')}>↩ Critical angle</button>
                    <button className={'cw-btn ' + (mode === 'fibre' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('fibre')}>🧵 Optical fibre</button>
                </div>
                {mode === 'refract' && (
                    <>
                        <Stat label="refractive index  n = sin i / sin r" value={(Math.sin(iRad) / Math.max(1e-3, Math.sin(rRefract * Math.PI / 180))).toFixed(2)} tone="acc"
                              sub={<>entering glass the ray <b>slows and bends toward the normal</b> (r &lt; i); n stays ~1.5 whatever the angle — it is a property of the glass</>} />
                        <Slider label="angle of incidence" value={angle} min={0} max={80} step={1} onChange={setAngle} format={(x) => `${x}°`} />
                        <Flag kind="neutral">
                            Light slows down entering glass, so it <b>bends toward the normal</b> (angle of refraction &lt; angle of
                            incidence). Leaving the glass it speeds up and bends <b>away</b>, emerging parallel to the incident ray.
                            The ratio <b>n = sin i / sin r</b> is the same at every angle — that constant is the refractive index.
                        </Flag>
                    </>
                )}
                {mode === 'tir' && (
                    <>
                        <Stat label={tir ? 'total internal reflection' : 'refracting out'} value={`c = ${CRIT.toFixed(0)}°`} tone={tir ? 'warn' : 'acc'}
                              sub={<>raise the angle inside the glass: past the <b>critical angle</b> ({CRIT.toFixed(0)}°) no light escapes — it is all reflected. n = 1 / sin c</>} />
                        <Slider label="angle of incidence (inside the glass)" value={angle} min={0} max={80} step={1} onChange={setAngle} format={(x) => `${x}°`} />
                        <Flag kind="neutral">
                            Below the <b>critical angle</b> the ray refracts out (bending away from the normal), with a weak
                            reflection. Exactly at c the refracted ray grazes along the surface. <b>Past c, all the light is
                            reflected back inside</b> — total internal reflection. Since n = 1/sin c, a bigger n gives a smaller c.
                        </Flag>
                    </>
                )}
                {mode === 'fibre' && (
                    <>
                        <Stat label="optical fibre — light trapped by TIR" value="telecommunications" tone="acc"
                              sub={<>the ray hits the core–cladding boundary above the critical angle every time, so it is <b>totally internally reflected</b> and guided along the fibre, even round bends</>} />
                        <Flag kind="neutral">
                            An optical fibre is a thin glass core in lower-index cladding. Light entering shallowly always meets the
                            boundary <b>past the critical angle</b>, so it is <b>totally internally reflected</b> at every bounce and
                            carried along the fibre with almost no loss. In <b>telecommunications</b> this gives huge data capacity,
                            very low signal loss over long distances, no electrical interference, and security.
                        </Flag>
                    </>
                )}
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, angle })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
