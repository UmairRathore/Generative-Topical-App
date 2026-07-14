import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: earth_orbit_lab ──────────────────────────────────────────────────
// Bespoke 6.1.1 hero (immersive 2D). The Earth, Moon and Sun, and orbital speed.
//   SYSTEM: the Earth orbits the Sun (~365 days) on a near-circular ellipse; it spins
//           on a TILTED axis (~24 h); the Moon orbits the Earth (~1 month); a light
//           pulse crosses from the Sun to the Earth in ~500 s (about 8 minutes).
//   SPEED: average orbital speed v = 2πr / T, with radius and period sliders and a
//          live circumference (2πr) and speed readout.
// config: { mode, radius, period }

export default function EarthOrbitLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['system', 'speed'].includes(config.mode) ? config.mode : 'system');
    const [radius, setRadius] = useState(Number.isFinite(config.radius) ? config.radius : 150); // millions of km
    const [period, setPeriod] = useState(Number.isFinite(config.period) ? config.period : 365); // days
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, radius, period };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, radius: st.current.radius, period: st.current.period }),
            setState: (s) => { if (['system', 'speed'].includes(s?.mode)) setMode(s.mode); if (Number.isFinite(s?.radius)) setRadius(s.radius); if (Number.isFinite(s?.period)) setPeriod(s.period); },
        });
    }, [onReady]); // eslint-disable-line

    // v = 2πr / T ; r in metres (millions of km → m), T in seconds (days → s)
    const r_m = radius * 1e9;                 // 1 million km = 1e9 m
    const T_s = period * 24 * 3600;
    const circ_m = 2 * Math.PI * r_m;
    const v = circ_m / T_s;                    // m/s

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf;
        const draw = (now) => {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight;
            if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const faint = cssVar('--ink-faint', '#68877A'), ink = cssVar('--ink-soft', '#A6C4B3');
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', cyan = '#38BDF8', rose = '#FB7185';
            const S = st.current, t = now / 1000;
            // starfield bg
            ctx.fillStyle = '#05070d'; ctx.fillRect(0, 0, w, h);
            for (let i = 0; i < 60; i++) { const sx = (i * 137.5) % w, sy = (i * 71.3) % h; ctx.fillStyle = `rgba(255,255,255,${0.12 + 0.12 * Math.sin(i + t)})`; ctx.fillRect(sx, sy, 1.3, 1.3); }
            ctx.textAlign = 'center';

            const sun = (x, y, R) => { const g = ctx.createRadialGradient(x, y, 0, x, y, R * 2.4); g.addColorStop(0, 'rgba(251,191,36,0.95)'); g.addColorStop(0.4, 'rgba(251,146,60,0.5)'); g.addColorStop(1, 'rgba(251,191,36,0)'); ctx.fillStyle = g; ctx.beginPath(); ctx.arc(x, y, R * 2.4, 0, 7); ctx.fill(); ctx.fillStyle = '#FDB813'; ctx.beginPath(); ctx.arc(x, y, R, 0, 7); ctx.fill(); };

            if (S.mode === 'system') {
                const cx = w * 0.38, cy = h * 0.5;
                const orbR = Math.min(w * 0.28, h * 0.36);
                // Earth's orbit (near-circular ellipse)
                ctx.strokeStyle = 'rgba(160,196,179,0.25)'; ctx.lineWidth = 1;
                ctx.beginPath(); ctx.ellipse(cx, cy, orbR, orbR * 0.94, 0, 0, 7); ctx.stroke();
                sun(cx, cy, 16);
                ctx.fillStyle = amber; ctx.font = '600 10px Inter'; ctx.fillText('Sun', cx, cy + 34);
                // Earth position
                const ang = t * 0.35; const ex = cx + Math.cos(ang) * orbR, ey = cy + Math.sin(ang) * orbR * 0.94;
                // a few short rays: the Sun radiates light in all directions
                ctx.strokeStyle = 'rgba(253,224,71,0.4)'; ctx.lineWidth = 1;
                for (let i = 0; i < 8; i++) { const a = i * Math.PI / 4 + t * 0.15; ctx.beginPath(); ctx.moveTo(cx + Math.cos(a) * 19, cy + Math.sin(a) * 19); ctx.lineTo(cx + Math.cos(a) * 25, cy + Math.sin(a) * 25); ctx.stroke(); }
                // ── the Sun–Earth line = the orbital RADIUS r, and the path SUNLIGHT travels ──
                const ldx = ex - cx, ldy = ey - cy, llen = Math.hypot(ldx, ldy) || 1; const ux = ldx / llen, uy = ldy / llen; const perpx = -uy, perpy = ux;
                ctx.strokeStyle = 'rgba(251,191,36,0.32)'; ctx.lineWidth = 1; ctx.setLineDash([3, 3]);
                ctx.beginPath(); ctx.moveTo(cx + ux * 15, cy + uy * 15); ctx.lineTo(ex - ux * 11, ey - uy * 11); ctx.stroke(); ctx.setLineDash([]);
                ctx.fillStyle = amber; ctx.font = '700 10px Inter'; ctx.textAlign = 'center';
                ctx.fillText('r', cx + ux * llen * 0.5 + perpx * 10, cy + uy * llen * 0.5 + perpy * 10);
                // sunlight: glowing pulses streaming Sun -> Earth (takes ~500 s)
                for (let i = 0; i < 3; i++) { const lp = 0.12 + 0.76 * (((t * 0.45) + i / 3) % 1); const lx = cx + ldx * lp, ly = cy + ldy * lp; const gl = ctx.createRadialGradient(lx, ly, 0, lx, ly, 5); gl.addColorStop(0, 'rgba(255,244,160,0.95)'); gl.addColorStop(1, 'rgba(255,244,160,0)'); ctx.fillStyle = gl; ctx.beginPath(); ctx.arc(lx, ly, 5, 0, 7); ctx.fill(); }
                // Earth — a globe SPINNING on its tilted axis (~24 h), giving day & night
                const tilt = -23.5 * Math.PI / 180, R = 15, spin = t * 1.2;
                const eg = ctx.createRadialGradient(ex - R * 0.35, ey - R * 0.35, 1, ex, ey, R);
                eg.addColorStop(0, '#4c92f0'); eg.addColorStop(1, '#1d4589');
                ctx.fillStyle = eg; ctx.beginPath(); ctx.arc(ex, ey, R, 0, 7); ctx.fill();
                // continents = clusters of surface points, rotated in longitude by `spin` about the tilted pole
                const land = [[0.15, 0.15], [0.4, 0.3], [0.3, -0.15], [0.55, 0.05], [2.0, -0.05], [2.3, 0.35], [2.45, -0.3], [2.15, 0.15], [4.0, 0.45], [4.25, 0.1], [4.1, -0.35], [4.4, -0.05], [5.4, 0.25]];
                land.forEach(([lon, lat]) => {
                    const cl = Math.cos(lat), sxu = cl * Math.sin(lon + spin), syu = Math.sin(lat), szu = cl * Math.cos(lon + spin);
                    if (szu <= 0.03) return; // point is on the far side of the globe — hidden
                    const pxu = sxu * Math.cos(tilt) - syu * Math.sin(tilt), pyu = sxu * Math.sin(tilt) + syu * Math.cos(tilt);
                    ctx.fillStyle = '#22c55e'; ctx.globalAlpha = Math.min(1, 0.5 + szu); ctx.beginPath(); ctx.arc(ex + pxu * R, ey - pyu * R, 2 + 2.2 * szu, 0, 7); ctx.fill();
                });
                ctx.globalAlpha = 1;
                // day / night: shade the hemisphere facing away from the Sun
                const sdx = cx - ex, sdy = cy - ey, sd = Math.hypot(sdx, sdy) || 1, uxs = sdx / sd, uys = sdy / sd;
                ctx.save(); ctx.beginPath(); ctx.arc(ex, ey, R, 0, 7); ctx.clip();
                const ng = ctx.createLinearGradient(ex + uxs * R, ey + uys * R, ex - uxs * R, ey - uys * R);
                ng.addColorStop(0.4, 'rgba(4,6,18,0)'); ng.addColorStop(1, 'rgba(4,6,18,0.62)');
                ctx.fillStyle = ng; ctx.fillRect(ex - R, ey - R, R * 2, R * 2); ctx.restore();
                // tilted spin axis (poles) + an arrow hinting the spin direction
                ctx.strokeStyle = rose; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.moveTo(ex + Math.sin(tilt) * (R + 6), ey - Math.cos(tilt) * (R + 6)); ctx.lineTo(ex - Math.sin(tilt) * (R + 6), ey + Math.cos(tilt) * (R + 6)); ctx.stroke();
                const apx = ex + Math.sin(tilt) * (R + 6), apy = ey - Math.cos(tilt) * (R + 6);
                ctx.strokeStyle = 'rgba(251,113,133,0.8)'; ctx.lineWidth = 1.3; ctx.beginPath(); ctx.arc(apx, apy, 5, -0.4, 2.4); ctx.stroke();
                ctx.fillStyle = rose; ctx.beginPath(); ctx.moveTo(apx + 5, apy + 1); ctx.lineTo(apx + 2, apy + 3); ctx.lineTo(apx + 7, apy + 4); ctx.fill();
                ctx.fillStyle = ink; ctx.font = '600 10px Inter'; ctx.fillText('Earth (spins ~24 h)', ex, ey + R + 15);
                // Moon orbiting Earth
                const mAng = t * 2.2; const mx = ex + Math.cos(mAng) * 24, my = ey + Math.sin(mAng) * 24;
                ctx.strokeStyle = 'rgba(160,196,179,0.2)'; ctx.beginPath(); ctx.arc(ex, ey, 24, 0, 7); ctx.stroke();
                ctx.fillStyle = '#cbd5e1'; ctx.beginPath(); ctx.arc(mx, my, 4, 0, 7); ctx.fill();
                ctx.fillStyle = faint; ctx.font = '600 8px Inter'; ctx.fillText('Moon', mx, my - 8);
                // fact chips on the right
                const facts = [
                    ['Earth orbits the Sun', '≈ 365 days'],
                    ['Orbit shape', 'ellipse, nearly circular'],
                    ['Sun–Earth distance (r)', '≈ 150 million km'],
                    ['Earth spins on a tilted axis', '≈ 24 hours'],
                    ['Moon orbits the Earth', '≈ 1 month'],
                    ['Sunlight: Sun → Earth', '≈ 500 s (8 min)'],
                ];
                let fy = h * 0.2; ctx.textAlign = 'left';
                facts.forEach(([a, b]) => { ctx.fillStyle = acc; ctx.font = '700 11px Inter'; ctx.fillText(a, w * 0.62, fy); ctx.fillStyle = ink; ctx.font = '600 11px Inter'; ctx.fillText(b, w * 0.62, fy + 14); fy += 36; });
            }

            else { // speed: v = 2πr/T — the orbit SIZE tracks r and the orbit SPEED tracks 1/T
                const cx = w * 0.34, cy = h * 0.5;
                const vv = (2 * Math.PI * S.radius * 1e9) / (S.period * 24 * 3600); // m/s
                // visual orbit radius grows with r (bounded so it always fits)
                const base = Math.min(w * 0.2, h * 0.34);
                const orbR = base * (0.42 + 0.58 * (S.radius - 50) / 1450);
                sun(cx, cy, 12);
                ctx.strokeStyle = 'rgba(56,189,248,0.35)'; ctx.lineWidth = 1.5; ctx.beginPath(); ctx.arc(cx, cy, orbR, 0, 7); ctx.stroke();
                // one on-screen orbit takes screenPeriod seconds, scaled by the real period T
                const screenPeriod = Math.max(1.6, Math.min(26, S.period / 365 * 5));
                const ang = (t * 2 * Math.PI) / screenPeriod;
                const ex = cx + Math.cos(ang) * orbR, ey = cy + Math.sin(ang) * orbR;
                // a trailing arc makes the speed visible at a glance
                ctx.strokeStyle = 'rgba(52,211,153,0.45)'; ctx.lineWidth = 3; ctx.beginPath(); ctx.arc(cx, cy, orbR, ang - 0.55, ang); ctx.stroke();
                // radius line
                ctx.strokeStyle = 'rgba(251,191,36,0.6)'; ctx.setLineDash([4, 3]); ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(ex, ey); ctx.stroke(); ctx.setLineDash([]);
                ctx.fillStyle = amber; ctx.font = '600 10px Inter'; ctx.fillText('r', (cx + ex) / 2 + 6, (cy + ey) / 2 - 4);
                // planet
                ctx.fillStyle = '#3b82f6'; ctx.beginPath(); ctx.arc(ex, ey, 8, 0, 7); ctx.fill();
                // velocity arrow (tangent) whose LENGTH grows with the orbital speed v
                const tx = -Math.sin(ang), ty = Math.cos(ang); const Lv = 12 + Math.min(48, vv / 1000 * 0.9);
                ctx.strokeStyle = acc; ctx.lineWidth = 2.4; ctx.beginPath(); ctx.moveTo(ex, ey); ctx.lineTo(ex + tx * Lv, ey + ty * Lv); ctx.stroke();
                ctx.fillStyle = acc; ctx.beginPath(); ctx.moveTo(ex + tx * Lv, ey + ty * Lv); ctx.lineTo(ex + tx * (Lv - 6) - ty * 5, ey + ty * (Lv - 6) + tx * 5); ctx.lineTo(ex + tx * (Lv - 6) + ty * 5, ey + ty * (Lv - 6) - tx * 5); ctx.fill();
                ctx.fillStyle = acc; ctx.font = '700 11px Inter'; ctx.fillText('v', ex + tx * (Lv + 8), ey + ty * (Lv + 8));
                // equation panel
                ctx.textAlign = 'left'; ctx.fillStyle = ink; ctx.font = '800 20px "JetBrains Mono", monospace';
                ctx.fillText('v = 2πr / T', w * 0.62, h * 0.36);
                ctx.font = '600 12px Inter'; ctx.fillStyle = faint;
                ctx.fillText('distance in one orbit ÷ time', w * 0.62, h * 0.42);
                ctx.fillStyle = amber; ctx.font = '700 12px "JetBrains Mono", monospace';
                ctx.fillText(`r = ${S.radius} × 10⁹ m`, w * 0.62, h * 0.54);
                ctx.fillText(`T = ${S.period} days`, w * 0.62, h * 0.60);
                ctx.fillStyle = acc; ctx.font = '800 15px "JetBrains Mono", monospace';
                ctx.fillText(`v ≈ ${(vv / 1000).toFixed(1)} km/s`, w * 0.62, h * 0.70);
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
                    <span className="cw-badge">{mode === 'system' ? 'The Earth, Moon and Sun' : 'Orbital speed: v = 2πr / T'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'system' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('system')}>Earth · Moon · Sun</button>
                    <button className={'cw-btn ' + (mode === 'speed' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('speed')}>Orbital speed</button>
                </div>
                {mode === 'speed' && <>
                    <Slider label="Orbit radius r" min={50} max={1500} step={10} value={radius} onChange={setRadius} suffix=" ×10⁹ m" />
                    <Slider label="Orbital period T" min={30} max={4500} step={5} value={period} onChange={setPeriod} suffix=" days" />
                    <Stat label="Average orbital speed" value={`${(v / 1000).toFixed(1)} km/s`} tone="acc" sub={<>v = 2πr / T = (2 × π × {radius}×10⁹ m) ÷ ({period} days) ≈ <b>{(v / 1000).toFixed(1)} km/s</b>. The Earth's own value (r ≈ 150×10⁹ m, T ≈ 365 days) is about <b>30 km/s</b>.</>} />
                </>}
                {mode === 'system' && <Stat label="Our corner of space" value="Earth · Moon · Sun" tone="acc" sub={<>The <b>Earth orbits the Sun</b> in ≈365 days on a nearly circular ellipse, spins on a <b>tilted axis</b> in ≈24 h; the <b>Moon orbits the Earth</b> in ≈1 month; sunlight takes ≈<b>500 s</b> to reach us.</>} />}
                <Flag kind="neutral">
                    The <b>Earth</b> orbits the <b>Sun</b> once in about <b>365 days</b> on an <b>ellipse that is nearly circular</b>, and spins on its
                    <b> tilted axis</b> once in about <b>24 hours</b>. The <b>Moon</b> orbits the Earth in about <b>one month</b>, and light from the
                    Sun takes about <b>500 s</b> (8 minutes) to reach us. The <b>average orbital speed</b> of a body is
                    <b> v = 2πr / T</b> — the distance around one orbit (2πr) divided by the time for one orbit (T).
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, radius, period })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
