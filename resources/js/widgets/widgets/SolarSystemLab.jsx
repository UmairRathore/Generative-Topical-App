import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: solar_system_lab ─────────────────────────────────────────────────
// Bespoke 6.1.2 hero (immersive 2D). The Solar System and gravitational fields.
//   ORRERY: the Sun + eight planets in order, an asteroid belt and a comet; a focus
//           selector reads each planet's data (distance, period, density, surface
//           temperature, surface g and orbital speed). The velocity arrow shrinks for
//           outer planets — orbital speed decreases with distance from the Sun.
//   GRAVITY: the strength of a planet's surface field grows with its mass; a field
//           weakens with distance; the Sun's surface field dwarfs the planets'.
// config: { mode, focus, mass, dist }

const PLANETS = [
    { n: 'Mercury', au: 0.39, period: '88 days', density: 5.4, temp: '+167 °C', g: 3.7, v: 47.4, c: '#b8b8b8' },
    { n: 'Venus', au: 0.72, period: '225 days', density: 5.2, temp: '+464 °C', g: 8.9, v: 35.0, c: '#e6c07a' },
    { n: 'Earth', au: 1.00, period: '365 days', density: 5.5, temp: '+15 °C', g: 9.8, v: 29.8, c: '#3b82f6' },
    { n: 'Mars', au: 1.52, period: '687 days', density: 3.9, temp: '−65 °C', g: 3.7, v: 24.1, c: '#c1440e' },
    { n: 'Jupiter', au: 5.20, period: '11.9 years', density: 1.3, temp: '−110 °C', g: 24.8, v: 13.1, c: '#d9a066' },
    { n: 'Saturn', au: 9.58, period: '29.4 years', density: 0.7, temp: '−140 °C', g: 10.4, v: 9.7, c: '#e3d9a0' },
    { n: 'Uranus', au: 19.2, period: '84 years', density: 1.3, temp: '−195 °C', g: 8.7, v: 6.8, c: '#a0e3e0' },
    { n: 'Neptune', au: 30.1, period: '165 years', density: 1.6, temp: '−200 °C', g: 11.2, v: 5.4, c: '#3b6fe0' },
];

export default function SolarSystemLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['orrery', 'gravity'].includes(config.mode) ? config.mode : 'orrery');
    const [focus, setFocus] = useState(Number.isFinite(config.focus) ? config.focus : 2); // Earth
    const [mass, setMass] = useState(Number.isFinite(config.mass) ? config.mass : 50);     // planet mass %
    const [dist, setDist] = useState(Number.isFinite(config.dist) ? config.dist : 30);     // distance from centre %
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, focus, mass, dist };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, focus: st.current.focus, mass: st.current.mass, dist: st.current.dist }),
            setState: (s) => { if (['orrery', 'gravity'].includes(s?.mode)) setMode(s.mode); if (Number.isFinite(s?.focus)) setFocus(s.focus); if (Number.isFinite(s?.mass)) setMass(s.mass); if (Number.isFinite(s?.dist)) setDist(s.dist); },
        });
    }, [onReady]); // eslint-disable-line

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
            ctx.fillStyle = '#05070d'; ctx.fillRect(0, 0, w, h);
            for (let i = 0; i < 70; i++) { const sx = (i * 137.5) % w, sy = (i * 53.7) % h; ctx.fillStyle = `rgba(255,255,255,${0.1 + 0.1 * Math.sin(i + t)})`; ctx.fillRect(sx, sy, 1.2, 1.2); }
            ctx.textAlign = 'center';
            const sun = (x, y, R) => { const g = ctx.createRadialGradient(x, y, 0, x, y, R * 2.6); g.addColorStop(0, 'rgba(251,191,36,0.95)'); g.addColorStop(0.4, 'rgba(251,146,60,0.5)'); g.addColorStop(1, 'rgba(251,191,36,0)'); ctx.fillStyle = g; ctx.beginPath(); ctx.arc(x, y, R * 2.6, 0, 7); ctx.fill(); ctx.fillStyle = '#FDB813'; ctx.beginPath(); ctx.arc(x, y, R, 0, 7); ctx.fill(); };

            if (S.mode === 'orrery') {
                const cx = w * 0.34, cy = h * 0.5;
                const rMax = Math.min(w * 0.3, h * 0.44);
                // compressed radial scale (log-ish) so all 8 fit
                const rOf = (au) => rMax * (0.16 + 0.84 * Math.log10(1 + au) / Math.log10(1 + 30.1));
                sun(cx, cy, 12);
                // asteroid belt between Mars(1.52) and Jupiter(5.2) ~ 2.7 au
                const beltR = rOf(2.7); ctx.strokeStyle = 'rgba(160,196,179,0.18)';
                for (let i = 0; i < 40; i++) { const a = i * 0.618 * 6.283 + t * 0.05; const rr = beltR + (i % 5 - 2) * 2; ctx.fillStyle = 'rgba(180,180,180,0.5)'; ctx.beginPath(); ctx.arc(cx + Math.cos(a) * rr, cy + Math.sin(a) * rr, 1, 0, 7); ctx.fill(); }
                PLANETS.forEach((p, i) => {
                    const R = rOf(p.au);
                    ctx.strokeStyle = i === S.focus ? 'rgba(52,211,153,0.5)' : 'rgba(160,196,179,0.14)'; ctx.lineWidth = i === S.focus ? 1.5 : 1;
                    ctx.beginPath(); ctx.arc(cx, cy, R, 0, 7); ctx.stroke();
                    const ang = t * (0.5 / Math.sqrt(p.au)) + i * 0.7; const px = cx + Math.cos(ang) * R, py = cy + Math.sin(ang) * R;
                    ctx.fillStyle = p.c; ctx.beginPath(); ctx.arc(px, py, i === S.focus ? 6 : 3.5, 0, 7); ctx.fill();
                    if (i === S.focus) {
                        // velocity arrow (tangent), length ~ orbital speed
                        const tx = -Math.sin(ang), ty = Math.cos(ang), L = 6 + p.v; ctx.strokeStyle = acc; ctx.lineWidth = 2;
                        ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(px + tx * L, py + ty * L); ctx.stroke();
                        // gravity arrow toward Sun
                        const gx = (cx - px), gy = (cy - py), gl = Math.hypot(gx, gy); ctx.strokeStyle = rose; ctx.setLineDash([3, 2]);
                        ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(px + gx / gl * 22, py + gy / gl * 22); ctx.stroke(); ctx.setLineDash([]);
                        ctx.fillStyle = ink; ctx.font = '700 11px Inter'; ctx.fillText(p.n, px, py - 12);
                    }
                });
                // comet on eccentric orbit
                const ca = t * 0.3; const cr = rOf(2 + 6 * (0.5 + 0.5 * Math.cos(ca)));
                const cxp = cx + Math.cos(ca * 1.3) * cr, cyp = cy + Math.sin(ca * 1.3) * cr * 0.6;
                ctx.fillStyle = '#cbd5e1'; ctx.beginPath(); ctx.arc(cxp, cyp, 2, 0, 7); ctx.fill();
                ctx.strokeStyle = 'rgba(203,213,225,0.4)'; ctx.beginPath(); ctx.moveTo(cxp, cyp); ctx.lineTo(cxp + (cxp - cx) * 0.08, cyp + (cyp - cy) * 0.08); ctx.stroke();

                // data panel on right
                const p = PLANETS[S.focus]; ctx.textAlign = 'left';
                let dy = h * 0.16; const dx = w * 0.66;
                ctx.fillStyle = acc; ctx.font = '800 14px Inter'; ctx.fillText(`${S.focus + 1}. ${p.n}`, dx, dy); dy += 22;
                const rows = [['distance from Sun', `${p.au} AU`], ['orbital period', p.period], ['orbital speed', `${p.v} km/s`], ['density', `${p.density} g/cm³`], ['surface temp', p.temp], ['surface g', `${p.g} N/kg`]];
                rows.forEach(([a, b]) => { ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.fillText(a, dx, dy); ctx.fillStyle = ink; ctx.font = '700 11px Inter'; ctx.fillText(b, dx + w * 0.18, dy); dy += 18; });
                ctx.fillStyle = rose; ctx.font = '600 9px Inter'; ctx.fillText('↩ red = pull of Sun (keeps it in orbit)', dx, dy + 4);
                ctx.fillStyle = acc; ctx.fillText('→ green = orbital velocity', dx, dy + 18);
            }

            else { // gravity
                // left: a planet whose surface field strength ~ its mass
                const cx = w * 0.28, cy = h * 0.46; const R = 18 + S.mass * 0.28;
                const gStrength = S.mass / 50 * 9.8; // relative
                ctx.fillStyle = '#3b82f6'; ctx.beginPath(); ctx.arc(cx, cy, R, 0, 7); ctx.fill();
                // field arrows inward, density ~ mass
                const nArr = Math.round(6 + S.mass / 10); ctx.strokeStyle = 'rgba(251,113,133,0.8)'; ctx.lineWidth = 1.6;
                for (let i = 0; i < nArr; i++) { const a = i / nArr * 6.283; const r1 = R + 34, r2 = R + 8; const x1 = cx + Math.cos(a) * r1, y1 = cy + Math.sin(a) * r1, x2 = cx + Math.cos(a) * r2, y2 = cy + Math.sin(a) * r2; ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke(); const ah = 4; ctx.beginPath(); ctx.moveTo(x2, y2); ctx.lineTo(x2 + Math.cos(a + 2.6) * ah, y2 + Math.sin(a + 2.6) * ah); ctx.lineTo(x2 + Math.cos(a - 2.6) * ah, y2 + Math.sin(a - 2.6) * ah); ctx.fill(); }
                ctx.fillStyle = ink; ctx.font = '700 11px Inter'; ctx.fillText('planet', cx, cy + R + 26);
                ctx.fillStyle = amber; ctx.font = '800 13px Inter'; ctx.fillText(`surface g ≈ ${gStrength.toFixed(1)} N/kg`, cx, cy - R - 12);

                // right: field weakens with distance — a test point at distance `dist`
                const px = w * 0.55 + S.dist / 100 * (w * 0.4); const py = cy;
                ctx.strokeStyle = 'rgba(56,189,248,0.5)'; ctx.setLineDash([3, 3]); ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(px, py); ctx.stroke(); ctx.setLineDash([]);
                const dScaled = 1 + S.dist / 15; const gAtDist = gStrength / (dScaled * dScaled);
                ctx.fillStyle = '#cbd5e1'; ctx.beginPath(); ctx.arc(px, py, 5, 0, 7); ctx.fill();
                ctx.fillStyle = cyan; ctx.font = '700 11px Inter'; ctx.fillText(`g here ≈ ${gAtDist.toFixed(2)} N/kg`, px, py - 12);
                ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.fillText('field weakens with distance', (cx + px) / 2, py + 26);
                ctx.textAlign = 'center'; ctx.fillStyle = ink; ctx.font = '700 11px Inter';
                ctx.fillText('surface field ∝ planet mass · field falls with distance · Sun’s field ≫ any planet’s', w / 2, h - 12);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const p = PLANETS[focus];

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{mode === 'orrery' ? 'The Solar System' : 'Gravitational field strength'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'orrery' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('orrery')}>Solar System</button>
                    <button className={'cw-btn ' + (mode === 'gravity' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('gravity')}>Gravity</button>
                </div>
                {mode === 'orrery' && <>
                    <Slider label={`Focus planet: ${p.n}`} min={0} max={7} step={1} value={focus} onChange={setFocus} />
                    <Stat label={`${p.n} — orbital speed`} value={`${p.v} km/s`} tone="acc" sub={<>At {p.au} AU from the Sun. Orbital speed <b>decreases</b> with distance from the Sun (Mercury ≈47 km/s → Neptune ≈5.4 km/s) because the Sun's <b>gravitational field weakens</b> further out.</>} />
                </>}
                {mode === 'gravity' && <>
                    <Slider label="Planet mass" min={10} max={100} step={5} value={mass} onChange={setMass} suffix=" %" />
                    <Slider label="Distance from planet" min={0} max={100} step={5} value={dist} onChange={setDist} suffix=" %" />
                    <Stat label="Gravitational field strength" value="g depends on mass & distance" tone="acc" sub={<>At a planet's <b>surface</b>, g is bigger for a <b>more massive</b> planet. Moving <b>away</b> from the planet, g <b>decreases</b>. The <b>Sun</b> holds most of the Solar System's mass, so its surface field is far stronger than any planet's.</>} />
                </>}
                <Flag kind="neutral">
                    The <b>Solar System</b> is one star (the <b>Sun</b>), the <b>eight planets</b> in order, <b>minor planets</b> (dwarf planets like
                    Pluto, and asteroids in the <b>asteroid belt</b>), <b>moons</b> orbiting planets, and smaller bodies like <b>comets</b>. The
                    <b> gravitational attraction of the Sun</b> keeps the planets in orbit. A planet's <b>surface gravitational field</b> is stronger
                    for a <b>more massive</b> planet and <b>weakens with distance</b>; the <b>Sun</b> has most of the mass, so its field is strongest.
                    Both the Sun's field and the planets' <b>orbital speeds decrease</b> with distance from the Sun.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, focus, mass, dist })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
