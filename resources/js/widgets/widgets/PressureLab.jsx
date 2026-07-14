import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: pressure_lab ─────────────────────────────────────────────────────
// Bespoke Pressure hero simulator (5054 · 1.8). Two modes:
//   PRESS — a block on a soft surface with force and contact-area sliders: the
//   surface indents with p = F/A (not with F), computed live - the
//   snowshoe/stiletto comparison as one continuous experiment.
//   DEPTH — a tank with a movable probe: Δp = ρgΔh live as the probe sinks,
//   density presets, wall jets that spray harder lower down, and pressure
//   arrows always at right angles to the probe's faces.
// g = 9.8 N/kg. Scenario values are illustrative.
//
// config: { mode } · Notes contract: getState/setState carry
// {mode, force, area, depth, density}.

const G = 9.8;
const LIQUIDS = [
    { key: 'oil', label: 'oil', rho: 800 },
    { key: 'water', label: 'water', rho: 1000 },
    { key: 'brine', label: 'brine', rho: 1200 },
];

export default function PressureLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(config.mode === 'depth' ? 'depth' : 'press');
    const [force, setForce] = useState(600);
    const [area, setArea] = useState(0.02);       // m²
    const [depth, setDepth] = useState(0.6);      // m
    const [liqKey, setLiqKey] = useState('water');
    const cvRef = useRef(null);
    const animRef = useRef({ dent: 0, probeY: 0.6 });
    const st = useRef({}); st.current = { mode, force, area, depth, liqKey };

    const rho = LIQUIDS.find((l) => l.key === liqKey).rho;
    const pressure = force / area;
    const dp = rho * G * depth;

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, force: st.current.force, area: st.current.area, depth: st.current.depth, density: LIQUIDS.find((l) => l.key === st.current.liqKey).rho }),
            setState: (s) => {
                if (s?.mode === 'press' || s?.mode === 'depth') setMode(s.mode);
                if (typeof s?.force === 'number') setForce(Math.max(100, Math.min(1200, s.force)));
                if (typeof s?.area === 'number') setArea(Math.max(0.005, Math.min(0.08, s.area)));
                if (typeof s?.depth === 'number') setDepth(Math.max(0.1, Math.min(1.0, s.depth)));
                if (s?.density && LIQUIDS.some((l) => l.rho === s.density)) setLiqKey(LIQUIDS.find((l) => l.rho === s.density).key);
            },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, t = 0;
        const draw = () => {
            t += 0.016;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return;
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
            const cyan = '#38BDF8', amber = '#FBBF24';
            const S = st.current, a = animRef.current;

            if (S.mode === 'press') {
                const p = S.force / S.area;
                const targetDent = Math.min(46, (p / 120000) * 46);
                a.dent += (targetDent - a.dent) * 0.1;
                const groundY = h * 0.62, cx = w * 0.42;
                const bw = Math.sqrt(S.area) * 560;                     // px width ∝ √area
                // soft surface with dent
                ctx.fillStyle = 'rgba(160,200,175,.08)';
                ctx.beginPath();
                ctx.moveTo(20, h - 30); ctx.lineTo(20, groundY);
                ctx.lineTo(cx - bw / 2 - 26, groundY);
                ctx.quadraticCurveTo(cx - bw / 2, groundY, cx - bw / 2, groundY + a.dent);
                ctx.lineTo(cx + bw / 2, groundY + a.dent);
                ctx.quadraticCurveTo(cx + bw / 2, groundY, cx + bw / 2 + 26, groundY);
                ctx.lineTo(w - 20, groundY); ctx.lineTo(w - 20, h - 30);
                ctx.closePath(); ctx.fill();
                ctx.strokeStyle = ink; ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.moveTo(20, groundY); ctx.lineTo(cx - bw / 2 - 26, groundY);
                ctx.quadraticCurveTo(cx - bw / 2, groundY, cx - bw / 2, groundY + a.dent);
                ctx.lineTo(cx + bw / 2, groundY + a.dent);
                ctx.quadraticCurveTo(cx + bw / 2, groundY, cx + bw / 2 + 26, groundY);
                ctx.lineTo(w - 20, groundY);
                ctx.stroke();
                // block + force arrow
                ctx.fillStyle = 'rgba(56,189,248,.14)'; ctx.strokeStyle = cyan; ctx.lineWidth = 2;
                const bh = 34 + S.force / 24;
                ctx.beginPath(); ctx.roundRect(cx - bw / 2, groundY + a.dent - bh, bw, bh, 5); ctx.fill(); ctx.stroke();
                ctx.strokeStyle = acc; ctx.fillStyle = acc; ctx.lineWidth = 3;
                const al = 20 + S.force / 18;
                ctx.beginPath(); ctx.moveTo(cx, groundY + a.dent - bh - al - 8); ctx.lineTo(cx, groundY + a.dent - bh - 6); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(cx, groundY + a.dent - bh); ctx.lineTo(cx - 5, groundY + a.dent - bh - 8); ctx.lineTo(cx + 5, groundY + a.dent - bh - 8); ctx.fill();
                ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(`force ${S.force} N`, cx, groundY + a.dent - bh - al - 14);
                ctx.fillStyle = faint;
                ctx.fillText(`contact area ${(S.area * 10000).toFixed(0)} cm²`, cx, groundY + a.dent + 18);
                // live equation
                ctx.font = '700 11.5px "JetBrains Mono", monospace'; ctx.fillStyle = amber;
                ctx.fillText(`p = F / A = ${S.force} / ${S.area.toFixed(3)} m²`, cx, h - 44);
                ctx.fillStyle = acc;
                ctx.fillText(`= ${Math.round(p).toLocaleString()} N/m² (Pa)`, cx, h - 26);
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif';
                ctx.fillText('the dent follows the PRESSURE, not the force', cx, h - 10);
            } else {
                // ── DEPTH mode ──
                const L = LIQUIDS.find((x) => x.key === S.liqKey);
                a.probeY += (S.depth - a.probeY) * 0.1;
                const tx = w * 0.30, tw = w * 0.34, topY = 40, botY = h - 40;
                const Ym = (m) => topY + (m / 1.0) * (botY - topY);
                // liquid
                const grad = ctx.createLinearGradient(0, topY, 0, botY);
                grad.addColorStop(0, 'rgba(56,189,248,.12)'); grad.addColorStop(1, `rgba(56,189,248,${0.16 + L.rho / 4000})`);
                ctx.fillStyle = grad; ctx.fillRect(tx, topY, tw, botY - topY);
                ctx.strokeStyle = ink; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.moveTo(tx, topY); ctx.lineTo(tx, botY); ctx.lineTo(tx + tw, botY); ctx.lineTo(tx + tw, topY); ctx.stroke();
                ctx.strokeStyle = line; ctx.setLineDash([3, 4]);
                ctx.beginPath(); ctx.moveTo(tx, topY); ctx.lineTo(tx + tw, topY); ctx.stroke(); ctx.setLineDash([]);
                // depth rule
                ctx.font = '600 8px "JetBrains Mono", monospace'; ctx.fillStyle = faint; ctx.textAlign = 'left';
                for (let m = 0; m <= 1.0001; m += 0.2) {
                    ctx.strokeStyle = line; ctx.beginPath(); ctx.moveTo(tx - 6, Ym(m)); ctx.lineTo(tx, Ym(m)); ctx.stroke();
                    ctx.fillText(`${m.toFixed(1)} m`, tx - 44, Ym(m) + 2.5);
                }
                // wall jets: spray length ∝ depth
                [0.25, 0.55, 0.85].forEach((dm) => {
                    const jy = Ym(dm), jl = 14 + dm * (L.rho / 1000) * 46;
                    ctx.strokeStyle = cyan; ctx.lineWidth = 2;
                    ctx.beginPath(); ctx.moveTo(tx + tw, jy);
                    ctx.quadraticCurveTo(tx + tw + jl * 0.7, jy + 4, tx + tw + jl, jy + 14 + dm * 10);
                    ctx.stroke();
                });
                ctx.fillStyle = faint; ctx.font = '600 8.5px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText('deeper holes spray harder', tx + tw + 8, topY + 12);
                // probe with perpendicular arrows
                const py = Ym(a.probeY), pxx = tx + tw * 0.45;
                ctx.fillStyle = 'rgba(251,191,36,.2)'; ctx.strokeStyle = amber; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.roundRect(pxx - 13, py - 13, 26, 26, 4); ctx.fill(); ctx.stroke();
                const arr = 10 + (L.rho * G * a.probeY) / 12000 * 26;
                [[0, -1], [0, 1], [-1, 0], [1, 0]].forEach(([dx, dy]) => {
                    ctx.strokeStyle = acc; ctx.fillStyle = acc; ctx.lineWidth = 2;
                    const x0 = pxx + dx * (13 + arr), y0 = py + dy * (13 + arr);
                    ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(pxx + dx * 14, py + dy * 14); ctx.stroke();
                    ctx.beginPath();
                    ctx.moveTo(pxx + dx * 13, py + dy * 13);
                    ctx.lineTo(pxx + dx * 21 - dy * 4, py + dy * 21 - dx * 4);
                    ctx.lineTo(pxx + dx * 21 + dy * 4, py + dy * 21 + dx * 4);
                    ctx.fill();
                });
                ctx.fillStyle = faint; ctx.font = '600 8.5px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('pressure pushes at right angles to every face', pxx, botY + 16);
                // depth brace + live equation
                ctx.strokeStyle = amber; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.moveTo(tx + tw + 62, topY); ctx.lineTo(tx + tw + 68, topY);
                ctx.lineTo(tx + tw + 68, py); ctx.lineTo(tx + tw + 62, py); ctx.stroke();
                ctx.fillStyle = amber; ctx.font = '700 10px "JetBrains Mono", monospace'; ctx.textAlign = 'left';
                ctx.fillText(`Δh = ${a.probeY.toFixed(2)} m`, tx + tw + 74, (topY + py) / 2 + 3);
                const px2 = w * 0.84;
                ctx.textAlign = 'center';
                ctx.fillStyle = faint; ctx.fillText('Δp = ρ g Δh', px2, h * 0.44);
                ctx.fillStyle = acc;
                ctx.fillText(`= ${L.rho} × 9.8 × ${a.probeY.toFixed(2)}`, px2, h * 0.44 + 18);
                ctx.fillText(`= ${Math.round(L.rho * G * a.probeY).toLocaleString()} Pa`, px2, h * 0.44 + 36);
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
                    <span className="cw-badge">{mode === 'press' ? 'Force over area' : 'Depth × density × g'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'press' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('press')}>⬇ Press test</button>
                    <button className={'cw-btn ' + (mode === 'depth' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('depth')}>🌊 Depth probe</button>
                </div>
                {mode === 'press' ? (
                    <>
                        <Stat label="pressure on the surface"
                              value={Math.round(pressure).toLocaleString()} unit="N/m² (Pa)" tone="acc"
                              sub={<>the dent tracks <b>p = F/A</b>: same force on a quarter of the area presses four times as hard — snowshoes and stiletto heels are the two ends of this slider</>} />
                        <Slider label="force pressing down" value={force} min={100} max={1200} step={50} onChange={setForce} format={(x) => `${x.toFixed(0)} N`} />
                        <Slider label="contact area" value={area} min={0.005} max={0.08} step={0.005} onChange={setArea} format={(x) => `${(x * 10000).toFixed(0)} cm²`} />
                        <Flag kind="neutral">
                            Double the force and the dent deepens; double the <b>area</b> and it relaxes — the surface never feels
                            the force alone, only the force <b>per unit area</b>. Find two slider settings with the same pressure
                            and watch the dent agree.
                        </Flag>
                    </>
                ) : (
                    <>
                        <Stat label="extra pressure at the probe"
                              value={Math.round(dp).toLocaleString()} unit="Pa" tone="acc"
                              sub={<>Δp = ρgΔh grows with <b>depth</b> and with <b>density</b> — and pushes at right angles to every face of the probe</>} />
                        <Slider label="probe depth" value={depth} min={0.1} max={1.0} step={0.05} onChange={setDepth} format={(x) => `${x.toFixed(2)} m`} />
                        <div className="cw-btnrow">
                            {LIQUIDS.map((l) => (
                                <button key={l.key} className={'cw-btn ' + (l.key === liqKey ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setLiqKey(l.key)}>
                                    {l.label} ({l.rho})
                                </button>
                            ))}
                        </div>
                        <Flag kind="neutral">
                            Sink the probe and watch <b>Δp = ρgΔh</b> climb; switch to brine and the same depth costs more pascals.
                            The wall jets tell the same story — the deepest hole sprays hardest — and the arrows never stop being
                            <b> perpendicular</b> to the faces.
                        </Flag>
                    </>
                )}
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, force, area, depth, density: rho })}>📌 Save this reading to my notes</button>
                )}
            </div>
        </div>
    );
}
