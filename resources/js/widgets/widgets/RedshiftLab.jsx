import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: redshift_lab ─────────────────────────────────────────────────────
// Bespoke 6.2.3 hero (immersive 2D). The Universe, redshift and the Big Bang.
//   REDSHIFT: a receding galaxy's spectral lines shift toward the RED (longer
//             wavelength) compared with a lab reference; a distance slider shows that
//             a further galaxy recedes faster and shows a greater redshift.
//   EXPANSION: the Universe is billions of galaxies (the Milky Way one of them, about
//             100 000 ly across) all moving apart; rewinding the expansion brings them
//             back to a single point — evidence for the Big Bang.
// config: { mode, distance, rewind }

export default function RedshiftLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['redshift', 'expansion'].includes(config.mode) ? config.mode : 'redshift');
    const [distance, setDistance] = useState(Number.isFinite(config.distance) ? config.distance : 40); // % (further = faster)
    const [rewind, setRewind] = useState(Number.isFinite(config.rewind) ? config.rewind : 100);        // 100 = now, 0 = Big Bang
    const cvRef = useRef(null);
    const galsRef = useRef(null);
    const st = useRef({}); st.current = { mode, distance, rewind };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, distance: st.current.distance, rewind: st.current.rewind }),
            setState: (s) => { if (['redshift', 'expansion'].includes(s?.mode)) setMode(s.mode); if (Number.isFinite(s?.distance)) setDistance(s.distance); if (Number.isFinite(s?.rewind)) setRewind(s.rewind); },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        // fixed galaxy field for expansion mode (unit vectors from centre)
        if (!galsRef.current) { galsRef.current = Array.from({ length: 34 }, (_, i) => { const a = i * 2.399; const r = 0.15 + 0.85 * ((i * 13) % 10) / 10; return { dx: Math.cos(a) * r, dy: Math.sin(a) * r * 0.7, milky: i === 5 }; }); }
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
            ctx.textAlign = 'center';

            if (S.mode === 'redshift') {
                const shift = S.distance / 100; // 0..1 fraction of shift toward red
                // two spectrum bars: lab reference (top) and receding galaxy (bottom)
                const bx = w * 0.06, bw = w * 0.62, bh = 26;
                const spectrum = (x, y, wdt, ht) => { const g = ctx.createLinearGradient(x, 0, x + wdt, 0); g.addColorStop(0, '#8b5cf6'); g.addColorStop(0.2, '#3b82f6'); g.addColorStop(0.4, '#22c55e'); g.addColorStop(0.6, '#eab308'); g.addColorStop(0.8, '#f97316'); g.addColorStop(1, '#ef4444'); ctx.fillStyle = g; ctx.fillRect(x, y, wdt, ht); };
                // reference lines at fixed positions
                const refLines = [0.25, 0.42, 0.55];
                spectrum(bx, h * 0.16, bw, bh);
                ctx.fillStyle = ink; ctx.font = '700 11px Inter'; ctx.textAlign = 'left'; ctx.fillText('lab reference (source at rest)', bx, h * 0.16 - 6);
                refLines.forEach(f => { const x = bx + bw * f; ctx.fillStyle = '#0a0d14'; ctx.fillRect(x - 1.5, h * 0.16, 3, bh); });
                spectrum(bx, h * 0.44, bw, bh);
                ctx.fillStyle = ink; ctx.fillText('light from a receding galaxy', bx, h * 0.44 - 6);
                refLines.forEach(f => { const fx = Math.min(0.97, f + shift * 0.4); const x = bx + bw * fx; ctx.fillStyle = '#0a0d14'; ctx.fillRect(x - 1.5, h * 0.44, 3, bh);
                    // arrow from ref to shifted
                    const x0 = bx + bw * f; ctx.strokeStyle = rose; ctx.lineWidth = 1.2; ctx.beginPath(); ctx.moveTo(x0, h * 0.44 + bh + 6); ctx.lineTo(x, h * 0.44 + bh + 6); ctx.stroke(); ctx.fillStyle = rose; ctx.beginPath(); ctx.moveTo(x, h * 0.44 + bh + 6); ctx.lineTo(x - 4, h * 0.44 + bh + 3); ctx.lineTo(x - 4, h * 0.44 + bh + 9); ctx.fill(); });
                ctx.fillStyle = rose; ctx.font = '700 10px Inter'; ctx.textAlign = 'center'; ctx.fillText('lines shifted toward RED (longer wavelength) = REDSHIFT', bx + bw / 2, h * 0.44 + bh + 24);
                // stretched wave
                const wy = h * 0.78; ctx.strokeStyle = amber; ctx.lineWidth = 2; ctx.beginPath();
                const lam = 12 + shift * 26;
                for (let x = 0; x <= bw; x++) { const yy = wy + Math.sin((x + t * 40) / lam * 6.283) * 8; if (x === 0) ctx.moveTo(bx + x, yy); else ctx.lineTo(bx + x, yy); }
                ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.textAlign = 'left'; ctx.fillText(`wavelength ${shift > 0.5 ? 'stretched' : 'slightly stretched'}`, bx, wy + 24);
                // recession galaxy on the right + speed
                const gx = w * 0.82, gy = h * 0.4; ctx.save(); ctx.translate(gx, gy); ctx.rotate(t * 0.1); for (let k = 0; k < 40; k++) { const a = k * 0.4; const rr = k * 0.5; ctx.fillStyle = `rgba(${200},${180 + k % 40},255,${0.6 - k / 80})`; ctx.beginPath(); ctx.arc(Math.cos(a) * rr, Math.sin(a) * rr * 0.6, 1.3, 0, 7); ctx.fill(); } ctx.restore();
                // recession arrow
                ctx.strokeStyle = rose; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(gx, gy + 24); ctx.lineTo(gx + 6 + shift * 30, gy + 24); ctx.stroke();
                ctx.fillStyle = ink; ctx.font = '700 10px Inter'; ctx.textAlign = 'center'; ctx.fillText('receding →', gx, gy + 40);
                ctx.fillStyle = rose; ctx.font = '800 12px Inter'; ctx.fillText(`speed ${(S.distance / 100 * 12).toFixed(1)}% c`, gx, gy - 30);
            }

            else { // expansion
                const cx = w / 2, cy = h * 0.44;
                const scale = 0.3 + (S.rewind / 100) * (Math.min(w, h) * 0.42 - 0.3) / 1; // radius scale from rewind
                const R = 8 + (S.rewind / 100) * Math.min(w * 0.42, h * 0.4);
                // galaxies spread out by rewind (100 = spread, 0 = point)
                galsRef.current.forEach((g) => {
                    const px = cx + g.dx * R, py = cy + g.dy * R;
                    if (g.milky) { ctx.fillStyle = amber; ctx.save(); ctx.translate(px, py); for (let k = 0; k < 20; k++) { const a = k * 0.5; ctx.beginPath(); ctx.arc(Math.cos(a) * k * 0.35, Math.sin(a) * k * 0.22, 1, 0, 7); ctx.fill(); } ctx.restore(); ctx.fillStyle = amber; ctx.font = '600 9px Inter'; ctx.fillText('Milky Way', px, py - 10); }
                    else { ctx.save(); ctx.translate(px, py); ctx.rotate(g.dx * 6); for (let k = 0; k < 14; k++) { const a = k * 0.5; ctx.fillStyle = `rgba(190,200,255,${0.6 - k / 30})`; ctx.beginPath(); ctx.arc(Math.cos(a) * k * 0.3, Math.sin(a) * k * 0.18, 1, 0, 7); ctx.fill(); } ctx.restore(); }
                });
                // outward arrows to show expansion (only when spread)
                if (S.rewind > 30) { galsRef.current.filter((_, i) => i % 4 === 0).forEach(g => { const px = cx + g.dx * R, py = cy + g.dy * R; const l = Math.hypot(g.dx, g.dy) || 1; ctx.strokeStyle = 'rgba(52,211,153,0.5)'; ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(px + g.dx / l * 12, py + g.dy / l * 12); ctx.stroke(); }); }
                ctx.fillStyle = ink; ctx.font = '700 12px Inter';
                if (S.rewind < 12) { const fg = ctx.createRadialGradient(cx, cy, 0, cx, cy, 40); fg.addColorStop(0, 'rgba(255,241,180,0.9)'); fg.addColorStop(1, 'rgba(0,0,0,0)'); ctx.fillStyle = fg; ctx.beginPath(); ctx.arc(cx, cy, 40, 0, 7); ctx.fill(); ctx.fillStyle = amber; ctx.font = '800 14px Inter'; ctx.fillText('the Big Bang', cx, cy + 58); ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.fillText('everything started from one point', cx, cy + 74); }
                else { ctx.fillStyle = ink; ctx.font = '700 11px Inter'; ctx.fillText(S.rewind > 80 ? 'the Universe now — billions of galaxies, all moving apart' : 'rewinding... galaxies were closer together', cx, h * 0.86); }
                ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.fillText('drag the timeline: now → back to the Big Bang', cx, h - 10);
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
                    <span className="cw-badge">{mode === 'redshift' ? 'Redshift of receding galaxies' : 'Expanding Universe & the Big Bang'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'redshift' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('redshift')}>Redshift</button>
                    <button className={'cw-btn ' + (mode === 'expansion' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('expansion')}>Expanding Universe</button>
                </div>
                {mode === 'redshift' && <>
                    <Slider label="Distance to the galaxy" min={0} max={100} step={5} value={distance} onChange={setDistance} suffix=" %" />
                    <Stat label="Redshift" value={`${distance > 60 ? 'large' : distance > 25 ? 'moderate' : 'small'} redshift`} tone="acc" sub={<>The galaxy's spectral lines shift toward the <b>red</b> (longer wavelength). A <b>further</b> galaxy recedes <b>faster</b> and shows a <b>greater</b> redshift.</>} />
                </>}
                {mode === 'expansion' && <>
                    <Slider label="Timeline (now → past)" min={0} max={100} step={5} value={rewind} onChange={setRewind} suffix="" />
                    <Stat label="An expanding Universe" value={rewind < 12 ? 'the Big Bang' : 'galaxies moving apart'} tone="acc" sub={<>The <b>Milky Way</b> is one of <b>billions of galaxies</b> making up the <b>Universe</b>. They are all moving <b>apart</b>. Rewinding brings them to a single point — the <b>Big Bang</b>.</>} />
                </>}
                <Flag kind="neutral">
                    The <b>Milky Way</b> is one of <b>many billions of galaxies</b> that make up the <b>Universe</b> (the Milky Way is about
                    <b> 100 000 light-years</b> across). <b>Redshift</b> is an <b>increase in the observed wavelength</b> of light from <b>receding</b>
                    stars and galaxies. Distant galaxies show redshift, and the <b>further</b> away a galaxy is, the <b>greater</b> its redshift and the
                    <b> faster</b> it is moving away. This shows the Universe is <b>expanding</b> — rewind that expansion and everything began at a single
                    point: evidence for the <b>Big Bang</b>.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, distance, rewind })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
