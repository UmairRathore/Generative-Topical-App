import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: expansion_lab ────────────────────────────────────────────────────
// Bespoke Thermal Expansion hero simulator (5054 · 2.2.1). Two modes:
//   EXPAND — a heated metal rod, a liquid-in-glass thermometer and a gas column
//   share one heat source; their expansions animate at honest relative
//   magnitudes (gas ≫ liquid > solid), the thermometer AS the application.
//   SCALES — one rendered thermometer read on two scales, °C and K, with the
//   +273 shift live down to the shared floor at absolute zero.
// Magnitudes are exaggerated evenly for visibility; the ORDER is the physics.
//
// Lake-bar immersion: lit lab bench, flame + heat shimmer, glass apparatus.
// Contract unchanged: config { mode } · getState/setState carry {mode, tC}.

export default function ExpansionLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(config.mode === 'scales' ? 'scales' : 'expand');
    const [tC, setTC] = useState(20);
    const cvRef = useRef(null);
    const animRef = useRef({ t: 20 });
    const st = useRef({}); st.current = { mode, tC };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, tC: st.current.tC }),
            setState: (s) => {
                if (s?.mode === 'expand' || s?.mode === 'scales') setMode(s.mode);
                if (typeof s?.tC === 'number') setTC(Math.max(mode === 'scales' ? -273 : 0, Math.min(100, s.tC)));
            },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, start = performance.now();
        const draw = (nowMs) => {
            const amb = (nowMs - start) / 1000;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
            const cyan = '#38BDF8', amber = '#FBBF24', rose = '#FB7185';
            const S = st.current, a = animRef.current;
            a.t += (S.tC - a.t) * 0.08;

            // lab room + bench
            const wall = ctx.createLinearGradient(0, 0, 0, h);
            wall.addColorStop(0, '#1f252d'); wall.addColorStop(1, '#191e24');
            ctx.fillStyle = wall; ctx.fillRect(0, 0, w, h);

            if (S.mode === 'expand') {
                const frac = Math.max(0, (a.t - 20) / 80);
                // warm heat glow from the left where the flames are
                const hg = ctx.createLinearGradient(0, 0, w * 0.5, 0);
                hg.addColorStop(0, `rgba(255,140,60,${0.05 + frac * 0.12})`); hg.addColorStop(1, 'rgba(255,140,60,0)');
                ctx.fillStyle = hg; ctx.fillRect(0, 0, w, h);
                const benchY = h - 26;
                ctx.fillStyle = '#333a43'; ctx.fillRect(0, benchY, w, h - benchY);

                const rows = [
                    { label: 'solid: metal rod', k: 0.06, col: '#c9d2dc', kind: 'rod' },
                    { label: 'liquid: in-glass thermometer', k: 0.30, col: '#ff5a6a', kind: 'therm' },
                    { label: 'gas: column in a tube', k: 1.0, col: '#8fe3c0', kind: 'gas' },
                ];
                const x0 = w * 0.30, baseLen = w * 0.26, maxExt = w * 0.30, rowH = (benchY - 30) / 3;
                const flame = (fx, fy) => {
                    for (let f = 0; f < 3; f++) {
                        const fh = (8 + frac * 16) * (1 - f * 0.2) + Math.sin(amb * 9 + f) * 2;
                        ctx.fillStyle = f % 2 ? `rgba(255,180,70,${0.5 + frac * 0.3})` : `rgba(255,90,60,${0.4 + frac * 0.3})`;
                        ctx.beginPath();
                        ctx.moveTo(fx - 5 + f * 3, fy);
                        ctx.quadraticCurveTo(fx - 2 + f * 3, fy - fh * 0.6, fx + f * 2, fy - fh);
                        ctx.quadraticCurveTo(fx + 4 + f * 3, fy - fh * 0.6, fx + 6 + f * 3, fy);
                        ctx.closePath(); ctx.fill();
                    }
                };
                ctx.font = '600 9.5px Inter, sans-serif';
                rows.forEach((r, i) => {
                    const y = 34 + i * rowH + rowH * 0.4;
                    ctx.fillStyle = ink; ctx.textAlign = 'left';
                    ctx.fillText(r.label, x0 - 4, y - 20);
                    // cold reference outline
                    ctx.strokeStyle = line; ctx.lineWidth = 1; ctx.setLineDash([3, 3]);
                    ctx.strokeRect(x0, y - 8, baseLen, 16); ctx.setLineDash([]);
                    const ext = frac * r.k * maxExt;
                    if (r.kind === 'therm') {
                        // bulb + capillary thread
                        ctx.fillStyle = '#e9eef3'; ctx.strokeStyle = '#aab3bd'; ctx.lineWidth = 2;
                        ctx.beginPath(); ctx.arc(x0 - 4, y, 11, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
                        ctx.fillStyle = r.col; ctx.beginPath(); ctx.arc(x0 - 4, y, 7, 0, Math.PI * 2); ctx.fill();
                        ctx.strokeStyle = '#aab3bd'; ctx.lineWidth = 6;
                        ctx.beginPath(); ctx.moveTo(x0 + 6, y); ctx.lineTo(x0 + baseLen + maxExt, y); ctx.stroke();
                        ctx.strokeStyle = r.col; ctx.lineWidth = 3.5;
                        ctx.beginPath(); ctx.moveTo(x0 + 6, y); ctx.lineTo(x0 + baseLen * 0.4 + ext, y); ctx.stroke();
                    } else if (r.kind === 'rod') {
                        const g = ctx.createLinearGradient(x0, 0, x0 + baseLen + ext, 0);
                        g.addColorStop(0, `rgba(255,${140 - frac * 60},60,${0.5 + frac * 0.4})`); g.addColorStop(0.4, r.col); g.addColorStop(1, '#8f98a2');
                        ctx.fillStyle = g; ctx.fillRect(x0, y - 7, baseLen + ext, 14);
                    } else {
                        ctx.fillStyle = `rgba(143,227,192,${0.28})`; ctx.fillRect(x0, y - 7, baseLen + ext, 14);
                        ctx.strokeStyle = '#aab3bd'; ctx.lineWidth = 1.5; ctx.strokeRect(x0, y - 8, baseLen + maxExt, 16);
                        // piston index
                        ctx.fillStyle = '#c9d2dc'; ctx.fillRect(x0 + baseLen + ext - 3, y - 9, 4, 18);
                    }
                    flame(x0 - 2, y + 22);
                    // expansion bracket
                    if (ext > 3) {
                        ctx.strokeStyle = r.col; ctx.setLineDash([2, 2]); ctx.lineWidth = 1;
                        ctx.beginPath(); ctx.moveTo(x0 + baseLen, y - 12); ctx.lineTo(x0 + baseLen + ext, y - 12); ctx.stroke(); ctx.setLineDash([]);
                        ctx.fillStyle = r.col; ctx.font = '700 8.5px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                        ctx.fillText(`+${(ext / maxExt * 100).toFixed(0)}`, x0 + baseLen + ext / 2, y - 16);
                        ctx.font = '600 9.5px Inter, sans-serif';
                    }
                });
                ctx.fillStyle = amber; ctx.font = '700 11px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(`${a.t.toFixed(0)} °C`, w * 0.14, h - 12);
                ctx.fillStyle = faint; ctx.font = '600 8.5px Inter, sans-serif';
                ctx.fillText('same heating — read the ORDER', w * 0.14, h - 26);
            } else {
                // ── SCALES: rendered dual-scale thermometer ──
                const tx = w * 0.36, topY = 30, botY = h - 40, tMin = -273, tMax = 100;
                const Y = (c) => botY - ((c - tMin) / (tMax - tMin)) * (botY - topY);
                // glass tube
                ctx.fillStyle = 'rgba(200,220,235,.06)';
                ctx.beginPath(); ctx.roundRect(tx - 8, topY - 8, 16, botY - topY + 20, 8); ctx.fill();
                ctx.strokeStyle = '#aab3bd'; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.roundRect(tx - 8, topY - 8, 16, botY - topY + 20, 8); ctx.stroke();
                // bulb
                const bulbG = ctx.createRadialGradient(tx - 3, botY + 8, 2, tx, botY + 11, 13);
                bulbG.addColorStop(0, '#ff8a96'); bulbG.addColorStop(1, '#c22f42');
                ctx.fillStyle = bulbG; ctx.beginPath(); ctx.arc(tx, botY + 11, 12, 0, Math.PI * 2); ctx.fill();
                // mercury thread
                const yNow = Y(a.t);
                const mg = ctx.createLinearGradient(tx - 4, 0, tx + 4, 0);
                mg.addColorStop(0, '#ff5a6a'); mg.addColorStop(0.5, '#ff9aa4'); mg.addColorStop(1, '#c22f42');
                ctx.fillStyle = mg; ctx.fillRect(tx - 4, yNow, 8, botY + 8 - yNow);
                // scales °C (left) / K (right)
                ctx.font = '600 8.5px "JetBrains Mono", monospace';
                for (let c = -273; c <= 100; c += (c === -273 ? 73 : 50)) {
                    const yy = Y(c);
                    ctx.strokeStyle = line; ctx.beginPath(); ctx.moveTo(tx - 26, yy); ctx.lineTo(tx - 10, yy); ctx.stroke();
                    ctx.fillStyle = faint; ctx.textAlign = 'right'; ctx.fillText(`${c} °C`, tx - 30, yy + 3);
                    ctx.strokeStyle = line; ctx.beginPath(); ctx.moveTo(tx + 10, yy); ctx.lineTo(tx + 26, yy); ctx.stroke();
                    ctx.fillStyle = faint; ctx.textAlign = 'left'; ctx.fillText(`${c + 273} K`, tx + 30, yy + 3);
                }
                ctx.strokeStyle = amber; ctx.lineWidth = 1.5; ctx.setLineDash([4, 3]);
                ctx.beginPath(); ctx.moveTo(tx - 62, yNow); ctx.lineTo(tx + 68, yNow); ctx.stroke(); ctx.setLineDash([]);
                const px2 = w * 0.76;
                ctx.font = '700 12px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillStyle = faint; ctx.fillText('T (in K) = θ (in °C) + 273', px2, h * 0.30);
                ctx.fillStyle = amber; ctx.fillText(`= ${a.t.toFixed(0)} + 273`, px2, h * 0.30 + 20);
                ctx.fillStyle = acc; ctx.fillText(`= ${(a.t + 273).toFixed(0)} K`, px2, h * 0.30 + 40);
                ctx.fillStyle = a.t <= -272 ? rose : faint; ctx.font = '600 9px Inter, sans-serif';
                ctx.fillText('one kelvin step = one Celsius degree', px2, h * 0.30 + 66);
                ctx.fillText('0 K = −273 °C: both scales share the floor', px2, h * 0.30 + 80);
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
                    <span className="cw-badge">{mode === 'expand' ? 'One heater · three expansions' : '°C and K · one temperature'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'expand' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => { setMode('expand'); setTC(Math.max(0, tC)); }}>🔥 Expansion race</button>
                    <button className={'cw-btn ' + (mode === 'scales' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('scales')}>🌡 Two scales</button>
                </div>
                {mode === 'expand' ? (
                    <>
                        <Stat label="relative order of expansion (same temperature rise)"
                              value="gas ≫ liquid > solid" tone="acc"
                              sub={<>the same heating expands the gas most, the liquid next, the solid least — and the liquid row IS a liquid-in-glass thermometer doing its job</>} />
                        <Slider label="temperature" value={Math.max(0, tC)} min={0} max={100} step={1} onChange={setTC} format={(x) => `${x.toFixed(0)} °C`} />
                    </>
                ) : (
                    <>
                        <Stat label="the same temperature on both scales"
                              value={`${(tC + 273).toFixed(0)} K`} tone="acc"
                              sub={<>T = θ + 273 = {tC.toFixed(0)} + 273 — one kelvin step equals one Celsius degree; only the zero moves</>} />
                        <Slider label="temperature" value={tC} min={-273} max={100} step={1} onChange={setTC} format={(x) => `${x.toFixed(0)} °C`} />
                    </>
                )}
                <Flag kind="neutral">
                    {mode === 'expand'
                        ? <>Heat all three together and read the <b>order</b>, not the sizes: gases expand far more than liquids, liquids more than solids — the drawn scale is exaggerated evenly so the order stays honest. The particle story: faster motion holds particles further apart; <b>the particles themselves never grow</b>.</>
                        : <>Slide anywhere and both labels move in lockstep: <b>T (in K) = θ (in °C) + 273</b>. At −273 °C the kelvin scale reads <b>0 K</b> — absolute zero is the shared floor, and kelvin simply starts counting there.</>}
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, tC })}>📌 Save this reading to my notes</button>
                )}
            </div>
        </div>
    );
}
