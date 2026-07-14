import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: phase_change_lab ─────────────────────────────────────────────────
// Bespoke 2.2.3 hero (immersive 2D). Melting, boiling, evaporation and latent heat.
//   HEATING: energy added at a steady rate to ice → water → steam. The temperature
//            rises, then PLATEAUS at 0 °C (melting) and 100 °C (boiling) where the
//            energy (LATENT HEAT) breaks the bonds between particles WITHOUT raising
//            the temperature. A live particle box shows lattice → sliding → free.
//   EVAPORATION: the most energetic particles escape from the SURFACE of a liquid at
//            any temperature; this removes energy so the liquid left behind COOLS.
//            Sliders for temperature, surface area and air movement change the rate;
//            a panel contrasts evaporation with boiling.
// config: { mode, energy, temp, area, air }

export default function PhaseChangeLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['heating', 'evaporation'].includes(config.mode) ? config.mode : 'heating');
    const [energy, setEnergy] = useState(Number.isFinite(config.energy) ? config.energy : 25);   // % energy added
    const [temp, setTemp] = useState(Number.isFinite(config.temp) ? config.temp : 50);            // liquid temp for evap
    const [area, setArea] = useState(Number.isFinite(config.area) ? config.area : 50);            // surface area
    const [air, setAir] = useState(Number.isFinite(config.air) ? config.air : 40);                // air movement
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, energy, temp, area, air };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, energy: st.current.energy, temp: st.current.temp, area: st.current.area, air: st.current.air }),
            setState: (s) => { if (['heating', 'evaporation'].includes(s?.mode)) setMode(s.mode); if (Number.isFinite(s?.energy)) setEnergy(s.energy); if (Number.isFinite(s?.temp)) setTemp(s.temp); if (Number.isFinite(s?.area)) setArea(s.area); if (Number.isFinite(s?.air)) setAir(s.air); },
        });
    }, [onReady]); // eslint-disable-line

    // heating curve: energy fraction e (0..1) -> temperature and phase
    const curve = (e) => {
        // segment boundaries in energy-fraction
        const b = [0, 0.14, 0.34, 0.58, 0.86, 1];
        if (e < b[1]) return { T: -25 + (25 / (b[1] - b[0])) * (e - b[0]), phase: 'solid', plateau: false };      // ice heating -25->0
        if (e < b[2]) return { T: 0, phase: 'melting', plateau: true };                                            // melting @0
        if (e < b[3]) return { T: 0 + (100 / (b[3] - b[2])) * (e - b[2]), phase: 'liquid', plateau: false };       // water 0->100
        if (e < b[4]) return { T: 100, phase: 'boiling', plateau: true };                                          // boiling @100
        return { T: 100 + (30 / (b[5] - b[4])) * (e - b[4]), phase: 'gas', plateau: false };                       // steam 100->130
    };

    // evaporation rate (relative), driven by temperature, surface area, air movement
    const evapRate = (temp / 100) * (0.4 + 0.6 * area / 100) * (0.5 + 0.5 * air / 100);

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, escaped = [];
        const draw = (now) => {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight;
            if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const faint = cssVar('--ink-faint', '#68877A'), ink = cssVar('--ink-soft', '#A6C4B3');
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', rose = '#FB7185', cyan = '#38BDF8';
            const S = st.current, t = now / 1000;
            const bg = ctx.createLinearGradient(0, 0, 0, h); bg.addColorStop(0, '#0f1420'); bg.addColorStop(1, '#0a0d14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);
            ctx.textAlign = 'center';

            if (S.mode === 'heating') {
                const e = S.energy / 100; const { T, phase, plateau } = curve(e);
                // graph on the right
                const gx0 = w * 0.44, gy0 = h * 0.12, gx1 = w * 0.94, gy1 = h * 0.82;
                const Tmin = -30, Tmax = 130;
                const ty = (temp) => gy1 - (gy1 - gy0) * (temp - Tmin) / (Tmax - Tmin);
                ctx.strokeStyle = faint; ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(gx0, gy0); ctx.lineTo(gx0, gy1); ctx.lineTo(gx1, gy1); ctx.stroke();
                // 0 and 100 gridlines
                [[0, '0 °C  melting'], [100, '100 °C  boiling']].forEach(([tv, lbl]) => { const y = ty(tv); ctx.strokeStyle = 'rgba(56,189,248,0.25)'; ctx.setLineDash([3, 3]); ctx.beginPath(); ctx.moveTo(gx0, y); ctx.lineTo(gx1, y); ctx.stroke(); ctx.setLineDash([]); ctx.fillStyle = cyan; ctx.font = '600 9px Inter'; ctx.textAlign = 'left'; ctx.fillText(lbl, gx0 + 4, y - 3); });
                ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.textAlign = 'center'; ctx.fillText('energy added →', (gx0 + gx1) / 2, gy1 + 16);
                ctx.save(); ctx.translate(gx0 - 16, (gy0 + gy1) / 2); ctx.rotate(-Math.PI / 2); ctx.fillText('temperature', 0, 0); ctx.restore();
                // the heating curve up to current energy
                ctx.strokeStyle = amber; ctx.lineWidth = 2.6; ctx.beginPath();
                for (let ee = 0; ee <= e + 0.001; ee += 0.005) { const { T: TT } = curve(ee); const x = gx0 + (gx1 - gx0) * ee; const y = ty(TT); if (ee === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y); }
                ctx.stroke();
                // current point
                const cx = gx0 + (gx1 - gx0) * e, cy = ty(T);
                ctx.fillStyle = plateau ? cyan : amber; ctx.beginPath(); ctx.arc(cx, cy, 5, 0, 7); ctx.fill();
                if (plateau) { ctx.fillStyle = cyan; ctx.font = '700 10px Inter'; ctx.textAlign = 'center'; ctx.fillText('latent heat — T constant', cx, cy - 12); }

                // particle box on the left
                const bx0 = w * 0.05, by0 = h * 0.16, bw = w * 0.32, bh = h * 0.58;
                ctx.strokeStyle = 'rgba(255,255,255,0.12)'; ctx.strokeRect(bx0, by0, bw, bh);
                const N = 28;
                // melted fraction & vaporised fraction from energy
                const meltFrac = e < 0.14 ? 0 : e < 0.34 ? (e - 0.14) / 0.20 : 1;
                const vapFrac = e < 0.58 ? 0 : e < 0.86 ? (e - 0.58) / 0.28 : 1;
                for (let i = 0; i < N; i++) {
                    const isGas = i / N < vapFrac; const isLiquid = !isGas && (i / N < meltFrac);
                    let px, py, col;
                    if (isGas) { // free flight in whole box
                        px = bx0 + 8 + ((i * 91 + t * 60 * (1 + i % 3)) % (bw - 16));
                        py = by0 + 8 + ((i * 53 + t * 45 * (1 + i % 2)) % (bh - 16));
                        col = rose;
                    } else if (isLiquid) { // sliding in lower 60%
                        const gx = i % 6, gyi = Math.floor(i / 6);
                        px = bx0 + 14 + gx * (bw - 28) / 5 + Math.sin(t * 2 + i) * 5;
                        py = by0 + bh * 0.45 + gyi * (bh * 0.5 / 4) + Math.cos(t * 2.3 + i) * 4;
                        col = cyan;
                    } else { // solid lattice vibrating
                        const gx = i % 6, gyi = Math.floor(i / 6);
                        px = bx0 + 16 + gx * (bw - 32) / 5 + Math.sin(t * 8 + i) * 1.5;
                        py = by0 + 14 + gyi * (bh - 28) / 4 + Math.cos(t * 8 + i * 1.3) * 1.5;
                        col = '#9aa4b2';
                    }
                    ctx.fillStyle = col; ctx.beginPath(); ctx.arc(px, py, 4.5, 0, 7); ctx.fill();
                }
                const phaseLbl = { solid: 'SOLID (ice)', melting: 'MELTING', liquid: 'LIQUID (water)', boiling: 'BOILING', gas: 'GAS (steam)' }[phase];
                ctx.fillStyle = ink; ctx.font = '700 12px Inter'; ctx.textAlign = 'center';
                ctx.fillText(phaseLbl, bx0 + bw / 2, by0 - 6);
                ctx.fillStyle = amber; ctx.font = '800 14px Inter'; ctx.fillText(`${T.toFixed(0)} °C`, bx0 + bw / 2, by0 + bh + 20);
            }

            else { // evaporation
                // liquid tank on the left, thermometer showing cooling
                const bx0 = w * 0.06, tankW = w * 0.42, surfY = h * 0.34, floorY = h * 0.82;
                const rate = (S.temp / 100) * (0.4 + 0.6 * S.area / 100) * (0.5 + 0.5 * S.air / 100);
                // liquid body
                ctx.fillStyle = 'rgba(56,189,248,0.16)'; ctx.fillRect(bx0, surfY, tankW, floorY - surfY);
                ctx.strokeStyle = 'rgba(56,189,248,0.4)'; ctx.strokeRect(bx0, surfY, tankW, floorY - surfY);
                ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.textAlign = 'left'; ctx.fillText('liquid surface', bx0 + 4, surfY - 4);
                // particles inside, coloured by speed (KE); fastest near surface escape
                for (let i = 0; i < 34; i++) {
                    const fast = (i % 5 === 0);
                    const px = bx0 + 10 + ((i * 71 + t * 30 * (fast ? 2 : 1)) % (tankW - 20));
                    const py = surfY + 12 + ((i * 47 + t * 22 * (fast ? 2.4 : 1)) % (floorY - surfY - 20));
                    ctx.fillStyle = fast ? amber : 'rgba(56,189,248,0.7)'; ctx.beginPath(); ctx.arc(px, py, fast ? 4 : 3, 0, 7); ctx.fill();
                }
                // escaping particles from the surface (rate-dependent)
                if (Math.random() < rate * 0.9) escaped.push({ x: bx0 + 10 + Math.random() * (tankW - 20), y: surfY, vx: (Math.random() - 0.5) * 30 - S.air * 0.2, vy: -30 - Math.random() * 40, life: 1 });
                escaped.forEach(p => { p.x += p.vx * 0.016; p.y += p.vy * 0.016; p.life -= 0.012; ctx.globalAlpha = Math.max(0, p.life); ctx.fillStyle = amber; ctx.beginPath(); ctx.arc(p.x, p.y, 3.5, 0, 7); ctx.fill(); ctx.globalAlpha = 1; });
                escaped = escaped.filter(p => p.life > 0 && p.y > 0);
                ctx.fillStyle = amber; ctx.font = '600 9px Inter'; ctx.textAlign = 'center'; ctx.fillText('most energetic particles escape', bx0 + tankW / 2, surfY - 18);

                // thermometer (cooling): the liquid left behind is cooler as fast particles leave
                const thX = bx0 + tankW + w * 0.08, thTop = h * 0.16, thBot = h * 0.78;
                ctx.strokeStyle = faint; ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(thX, thTop); ctx.lineTo(thX, thBot); ctx.stroke();
                ctx.fillStyle = 'rgba(255,255,255,0.08)'; ctx.beginPath(); ctx.arc(thX, thBot + 8, 9, 0, 7); ctx.fill();
                // reading drops with evaporation rate (cooling effect)
                const reading = Math.max(2, S.temp - rate * 22);
                const fillY = thBot - (thBot - thTop) * (reading / 100);
                ctx.strokeStyle = rose; ctx.lineWidth = 5; ctx.beginPath(); ctx.moveTo(thX, thBot); ctx.lineTo(thX, fillY); ctx.stroke();
                ctx.fillStyle = rose; ctx.beginPath(); ctx.arc(thX, thBot + 8, 6, 0, 7); ctx.fill();
                ctx.fillStyle = ink; ctx.font = '700 11px Inter'; ctx.textAlign = 'left'; ctx.fillText(`${reading.toFixed(0)} °C`, thX + 10, fillY + 4);
                ctx.fillStyle = cyan; ctx.font = '600 9px Inter'; ctx.fillText('cooling', thX + 10, fillY + 18);

                ctx.textAlign = 'center'; ctx.fillStyle = ink; ctx.font = '700 12px Inter';
                ctx.fillText(`evaporation rate: ${(rate * 100).toFixed(0)}  →  faster loss of energy  →  more cooling`, w / 2, h - 12);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const cur = curve(energy / 100);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{mode === 'heating' ? 'Heating curve: melting & boiling plateaux' : 'Evaporation & cooling'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'heating' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('heating')}>Heating curve</button>
                    <button className={'cw-btn ' + (mode === 'evaporation' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('evaporation')}>Evaporation</button>
                </div>
                {mode === 'heating' && <>
                    <Slider label="Energy added" min={0} max={100} step={1} value={energy} onChange={setEnergy} suffix=" %" />
                    <Stat label={{ solid: 'Heating the ice', melting: 'Melting (latent heat)', liquid: 'Heating the water', boiling: 'Boiling (latent heat)', gas: 'Heating the steam' }[cur.phase]} value={`${cur.T.toFixed(0)} °C`} tone={cur.plateau ? 'warn' : 'acc'} sub={cur.plateau
                        ? <>At a change of state the temperature stays <b>constant</b> while energy — the <b>latent heat</b> — goes into <b>breaking the bonds</b> between particles, not raising the temperature.</>
                        : <>While in one state, added energy raises the <b>temperature</b> (the particles gain kinetic energy). Water <b>melts at 0 °C</b> and <b>boils at 100 °C</b> at standard pressure.</>} />
                </>}
                {mode === 'evaporation' && <>
                    <Slider label="Temperature" min={5} max={95} step={5} value={temp} onChange={setTemp} suffix=" °C" />
                    <Slider label="Surface area" min={10} max={100} step={5} value={area} onChange={setArea} suffix=" %" />
                    <Slider label="Air movement (draught)" min={0} max={100} step={5} value={air} onChange={setAir} suffix=" %" />
                    <Stat label="Evaporation & cooling" value={`rate ${(evapRate * 100).toFixed(0)}`} tone="acc" sub={<>The <b>most energetic</b> particles escape from the <b>surface</b>, so the average energy of those left <b>falls</b> — the liquid <b>cools</b>. Rate rises with <b>temperature</b>, <b>surface area</b> and <b>air movement</b>.</>} />
                </>}
                <Flag kind="neutral">
                    <b>Melting, solidifying, boiling and condensing</b> happen at a <b>constant temperature</b> — the energy (the <b>latent heat</b>)
                    goes into <b>breaking or making the bonds</b> between particles, not changing their kinetic energy. Water <b>melts at 0 °C</b> and
                    <b> boils at 100 °C</b>. <b>Evaporation</b> is different from boiling: it happens only at the <b>surface</b>, at <b>any</b> temperature,
                    as the <b>most energetic</b> particles escape — which <b>cools</b> the liquid left behind. It is faster at higher <b>temperature</b>,
                    larger <b>surface area</b> and more <b>air movement</b>.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, energy, temp, area, air })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
