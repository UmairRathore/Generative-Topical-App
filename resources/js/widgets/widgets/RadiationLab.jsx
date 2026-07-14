import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag, Button } from '../primitives.jsx';

// ── Widget: radiation_lab ────────────────────────────────────────────────────
// Bespoke Thermal Radiation hero simulator (5054 · 2.3.3). Three modes cover
// the section: EMIT — a Leslie cube of hot water with four surfaces and an IR
// detector, so matt-black beats shiny and hotter/larger emits more (#2,#3,#4);
// ABSORB — a radiant heater between a matt-black and a shiny plate, the black
// one warming faster (#2,#5); SPACE — the Sun's infrared crossing the vacuum to
// Earth, the only transfer needing no medium (#1). Readings are illustrative.
//
// Lake-bar immersion: rendered apparatus + starfield. config: { mode } ·
// Notes contract: getState/setState carry {mode, tempC}.

const FACES = [
    { key: 'matt', label: 'matt black', e: 1.0, col: '#161616', kind: 'best' },
    { key: 'dull', label: 'dull grey', e: 0.78, col: '#5a5f66', kind: 'good' },
    { key: 'white', label: 'white', e: 0.38, col: '#e6e9ee', kind: 'poor' },
    { key: 'shiny', label: 'shiny silver', e: 0.1, col: '#cfd6de', kind: 'worst' },
];

export default function RadiationLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['emit', 'absorb', 'space'].includes(config.mode) ? config.mode : 'emit');
    const [faceKey, setFaceKey] = useState('matt');
    const [tempC, setTempC] = useState(80);
    const [running, setRunning] = useState(false);
    const [snap, setSnap] = useState({ tB: 20, tS: 20 });
    const simRef = useRef({ tB: 20, tS: 20 });
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, faceKey, tempC, running };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, tempC: st.current.tempC }),
            setState: (s) => {
                if (['emit', 'absorb', 'space'].includes(s?.mode)) setMode(s.mode);
                if (typeof s?.tempC === 'number') setTempC(Math.max(30, Math.min(100, s.tempC)));
            },
        });
    }, [onReady]); // eslint-disable-line

    const face = FACES.find((f) => f.key === faceKey);
    const emission = face.e * ((tempC - 15) / 85);   // 0..~1, scales with emissivity AND temperature

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, last = performance.now();
        const draw = (now) => {
            const dt = Math.min(0.05, (now - last) / 1000); last = now;
            const amb = now / 1000;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
            const amber = '#FBBF24', rose = '#FB7185';
            const S = st.current;

            if (S.mode === 'space') {
                // starfield vacuum
                ctx.fillStyle = '#05070d'; ctx.fillRect(0, 0, w, h);
                for (let i = 0; i < 60; i++) {
                    const sx = (Math.sin(i * 91.7) * 0.5 + 0.5) * w, sy = (Math.sin(i * 33.3) * 0.5 + 0.5) * h;
                    ctx.fillStyle = `rgba(255,255,255,${0.2 + (Math.sin(i * 7.1 + amb) * 0.5 + 0.5) * 0.5})`;
                    ctx.fillRect(sx, sy, 1.3, 1.3);
                }
                // Sun (left)
                const sunX = w * 0.16, sunY = h * 0.5;
                const sg = ctx.createRadialGradient(sunX, sunY, 6, sunX, sunY, 70);
                sg.addColorStop(0, '#fff2c0'); sg.addColorStop(0.4, 'rgba(255,180,80,.6)'); sg.addColorStop(1, 'rgba(255,140,60,0)');
                ctx.fillStyle = sg; ctx.beginPath(); ctx.arc(sunX, sunY, 70, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = '#ffe9a8'; ctx.beginPath(); ctx.arc(sunX, sunY, 28, 0, Math.PI * 2); ctx.fill();
                // Earth (right)
                const eX = w * 0.82, eY = h * 0.5;
                const eg = ctx.createRadialGradient(eX - 6, eY - 6, 3, eX, eY, 26);
                eg.addColorStop(0, '#6fb0e0'); eg.addColorStop(0.7, '#2d6a9a'); eg.addColorStop(1, '#14324a');
                ctx.fillStyle = eg; ctx.beginPath(); ctx.arc(eX, eY, 24, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = 'rgba(70,150,90,.6)'; ctx.beginPath(); ctx.arc(eX - 6, eY + 4, 8, 0, Math.PI * 2); ctx.fill();
                // IR streaming across the vacuum
                for (let i = 0; i < 7; i++) {
                    const p = ((amb * 0.3 + i / 7) % 1);
                    const x = sunX + 30 + p * (eX - sunX - 55);
                    const y = sunY + (i - 3) * 16;
                    ctx.strokeStyle = `rgba(255,170,90,${0.5 * Math.sin(p * Math.PI)})`; ctx.lineWidth = 2;
                    ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + 18, y); ctx.stroke();
                }
                ctx.fillStyle = ink; ctx.font = '600 11px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('infrared radiation crosses ~150 million km of empty space', w / 2, h - 40);
                ctx.fillStyle = amber; ctx.font = '700 11px Inter, sans-serif';
                ctx.fillText('no medium needed — a vacuum stops conduction and convection cold', w / 2, h - 22);
                raf = requestAnimationFrame(draw); return;
            }

            // lab bench (emit / absorb)
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#20262e'); bg.addColorStop(1, '#171c22');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);
            const benchY = h - 28;
            ctx.fillStyle = '#333a43'; ctx.fillRect(0, benchY, w, h - benchY);

            if (S.mode === 'emit') {
                const fc = FACES.find((f) => f.key === S.faceKey);
                const em = fc.e * ((S.tempC - 15) / 85);
                // Leslie cube in slight 3D, hot water inside (steam)
                const cx = w * 0.30, cy = benchY - 70, sz = 64;
                // top face
                ctx.fillStyle = '#3a3f47';
                ctx.beginPath(); ctx.moveTo(cx - sz / 2, cy - sz / 2); ctx.lineTo(cx - sz / 2 + 16, cy - sz / 2 - 14);
                ctx.lineTo(cx + sz / 2 + 16, cy - sz / 2 - 14); ctx.lineTo(cx + sz / 2, cy - sz / 2); ctx.closePath(); ctx.fill();
                // side face
                ctx.fillStyle = '#2a2f36';
                ctx.beginPath(); ctx.moveTo(cx + sz / 2, cy - sz / 2); ctx.lineTo(cx + sz / 2 + 16, cy - sz / 2 - 14);
                ctx.lineTo(cx + sz / 2 + 16, cy + sz / 2 - 14); ctx.lineTo(cx + sz / 2, cy + sz / 2); ctx.closePath(); ctx.fill();
                // front face (selected material)
                if (fc.key === 'shiny') {
                    const mg = ctx.createLinearGradient(cx - sz / 2, 0, cx + sz / 2, 0);
                    mg.addColorStop(0, '#8a929c'); mg.addColorStop(0.5, '#eef2f6'); mg.addColorStop(1, '#9aa2ac');
                    ctx.fillStyle = mg;
                } else ctx.fillStyle = fc.col;
                ctx.fillRect(cx - sz / 2, cy - sz / 2, sz, sz);
                ctx.strokeStyle = '#11151a'; ctx.lineWidth = 1.5; ctx.strokeRect(cx - sz / 2, cy - sz / 2, sz, sz);
                ctx.fillStyle = fc.key === 'white' || fc.key === 'shiny' ? '#333' : '#cfd6de';
                ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(fc.label, cx, cy + 2);
                // steam (hotter → more)
                for (let i = 0; i < 4; i++) {
                    const p = ((amb * 0.5 + i / 4) % 1);
                    ctx.strokeStyle = `rgba(220,230,240,${(0.2 + (S.tempC - 30) / 140) * (1 - p)})`; ctx.lineWidth = 2;
                    const stx = cx - 10 + i * 7, sty = cy - sz / 2 - 14 - p * 26;
                    ctx.beginPath(); ctx.moveTo(stx, sty); ctx.quadraticCurveTo(stx + 5, sty - 5, stx, sty - 10); ctx.stroke();
                }
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif';
                ctx.fillText(`Leslie cube · ${S.tempC.toFixed(0)} °C water`, cx, benchY + 14);
                // IR waves leaving the front face, density ∝ emission
                const nWave = Math.round(1 + em * 7);
                for (let i = 0; i < nWave; i++) {
                    const p = ((amb * 0.7 + i / nWave) % 1);
                    const wx = cx + sz / 2 + p * (w * 0.28);
                    ctx.strokeStyle = `rgba(255,150,80,${0.6 * Math.sin(p * Math.PI)})`; ctx.lineWidth = 2;
                    ctx.beginPath(); ctx.arc(wx, cy, 6, -0.5, 0.5); ctx.stroke();
                }
                // detector (thermopile) on the right
                const dx = w * 0.72;
                ctx.fillStyle = '#3a3f47'; ctx.beginPath(); ctx.roundRect(dx, cy - 26, 30, 52, 5); ctx.fill();
                ctx.strokeStyle = ink; ctx.lineWidth = 1.5; ctx.beginPath(); ctx.moveTo(dx, cy); ctx.lineTo(dx - 12, cy - 6); ctx.lineTo(dx - 12, cy + 6); ctx.closePath(); ctx.stroke();
                // detector bar
                ctx.fillStyle = 'rgba(0,0,0,.4)'; ctx.beginPath(); ctx.roundRect(dx + 44, cy - 44, 22, 100, 4); ctx.fill();
                ctx.fillStyle = em > 0.6 ? acc : em > 0.3 ? amber : rose;
                ctx.fillRect(dx + 47, cy + 53 - em * 94, 16, em * 94);
                ctx.fillStyle = ink; ctx.font = '700 10px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText('detector', dx + 55, cy - 52);
                ctx.fillStyle = faint; ctx.font = '600 8.5px Inter, sans-serif';
                ctx.fillText(fc.kind === 'best' ? 'BEST emitter' : fc.kind === 'worst' ? 'worst emitter' : `${fc.kind} emitter`, dx + 55, cy + 70);
            } else {
                // ABSORB: heater between two plates
                const sim = simRef.current;
                const heater = S.running ? 1 : 0;
                if (S.running) {
                    sim.tB = Math.min(90, sim.tB + 1.0 * dt * 14);        // matt black absorbs strongly
                    sim.tS = Math.min(90, sim.tS + 0.25 * dt * 14);       // shiny reflects most
                    setSnap({ tB: sim.tB, tS: sim.tS });
                }
                const cx = w * 0.5, cy = benchY - 60;
                // radiant heater (glowing coil)
                const hg = ctx.createRadialGradient(cx, cy, 4, cx, cy, 40);
                hg.addColorStop(0, 'rgba(255,120,60,.9)'); hg.addColorStop(1, 'rgba(255,120,60,0)');
                ctx.fillStyle = hg; ctx.beginPath(); ctx.arc(cx, cy, 40, 0, Math.PI * 2); ctx.fill();
                ctx.strokeStyle = '#ff8a4a'; ctx.lineWidth = 3;
                for (let i = -2; i <= 2; i++) { ctx.beginPath(); ctx.arc(cx, cy + i * 7, 8, 0, Math.PI * 2); ctx.stroke(); }
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('radiant heater', cx, benchY + 14);
                // IR arrows to both plates (equal)
                if (S.running) [-1, 1].forEach((s2) => {
                    for (let i = 0; i < 3; i++) {
                        const p = ((amb + i / 3) % 1);
                        const x = cx + s2 * (24 + p * (w * 0.16));
                        ctx.strokeStyle = `rgba(255,150,80,${0.5 * Math.sin(p * Math.PI)})`; ctx.lineWidth = 2;
                        ctx.beginPath(); ctx.moveTo(x, cy + (i - 1) * 10); ctx.lineTo(x + s2 * 12, cy + (i - 1) * 10); ctx.stroke();
                    }
                });
                // two plates with thermometers
                const plate = (px, col, temp, label, isBest) => {
                    if (col === 'shiny') {
                        const mg = ctx.createLinearGradient(px - 8, 0, px + 8, 0);
                        mg.addColorStop(0, '#8a929c'); mg.addColorStop(0.5, '#eef2f6'); mg.addColorStop(1, '#9aa2ac'); ctx.fillStyle = mg;
                    } else ctx.fillStyle = '#161616';
                    ctx.fillRect(px - 8, cy - 40, 16, 80);
                    ctx.strokeStyle = ink; ctx.lineWidth = 1; ctx.strokeRect(px - 8, cy - 40, 16, 80);
                    // thermometer readout
                    ctx.fillStyle = temp > 55 ? rose : temp > 35 ? amber : acc;
                    ctx.font = '700 12px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                    ctx.fillText(`${temp.toFixed(0)} °C`, px, cy - 50);
                    ctx.fillStyle = ink; ctx.font = '600 9px Inter, sans-serif';
                    ctx.fillText(label, px, cy + 56);
                    ctx.fillStyle = faint; ctx.font = '600 8px Inter, sans-serif';
                    ctx.fillText(isBest ? 'BEST absorber' : 'reflects most', px, cy + 68);
                };
                plate(w * 0.24, 'matt', sim.tB, 'matt black', true);
                plate(w * 0.76, 'shiny', sim.tS, 'shiny silver', false);
            }

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const reset = () => { simRef.current = { tB: 20, tS: 20 }; setSnap({ tB: 20, tS: 20 }); setRunning(false); };

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{mode === 'emit' ? 'Leslie cube · emitters' : mode === 'absorb' ? 'Two plates · absorbers' : 'Radiation across the vacuum'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'emit' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('emit')}>🔥 Emit</button>
                    <button className={'cw-btn ' + (mode === 'absorb' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => { setMode('absorb'); reset(); }}>🌡 Absorb</button>
                    <button className={'cw-btn ' + (mode === 'space' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('space')}>🌍 Space</button>
                </div>
                {mode === 'emit' && (
                    <>
                        <Stat label={`emission from the ${face.label} surface`}
                              value={face.kind === 'best' ? 'strongest' : face.kind === 'worst' ? 'weakest' : face.kind} tone="acc"
                              sub={<>matt black &gt; dull &gt; white &gt; shiny — and the rate rises with the surface's <b>temperature</b> (and its <b>area</b>)</>} />
                        <div className="cw-btnrow">
                            {FACES.map((f) => (
                                <button key={f.key} className={'cw-btn ' + (f.key === faceKey ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setFaceKey(f.key)}>{f.label}</button>
                            ))}
                        </div>
                        <Slider label="cube temperature" value={tempC} min={30} max={100} step={1} onChange={setTempC} format={(x) => `${x.toFixed(0)} °C`} />
                        <Flag kind="neutral">
                            Point the detector at each face of the same hot cube: the <b>matt black</b> face emits far more infrared
                            than the <b>shiny</b> one, though all four are at the same temperature. Raise the temperature and every
                            face emits more — the rate of emission grows with <b>surface temperature</b> and with <b>surface area</b>.
                        </Flag>
                    </>
                )}
                {mode === 'absorb' && (
                    <>
                        <Stat label="which plate warms faster"
                              value={`${snap.tB.toFixed(0)} vs ${snap.tS.toFixed(0)}`} unit="°C" tone="acc"
                              sub={<>equal radiation reaches both, but the <b>matt black</b> plate absorbs it while the <b>shiny</b> plate reflects most away</>} />
                        <div className="cw-btnrow">
                            <Button variant="save" onClick={() => setRunning(!running)}>{running ? '⏸ Pause' : '▶ Switch on the heater'}</Button>
                            <Button variant="ghost" onClick={reset}>↺ Reset</Button>
                        </div>
                        <Flag kind="neutral">
                            The same infrared falls on both plates, yet the <b>matt black</b> plate's thermometer races ahead: dull,
                            dark surfaces are the <b>best absorbers</b>; shiny, light surfaces <b>reflect</b> radiation away. A good
                            absorber is also a good emitter — the black face led both tests.
                        </Flag>
                    </>
                )}
                {mode === 'space' && (
                    <>
                        <Stat label="the transfer that needs no medium"
                              value="radiation only" tone="acc"
                              sub={<>the Sun's energy reaches Earth across empty space — conduction and convection both need particles, so only <b>radiation</b> can cross a vacuum</>} />
                        <Flag kind="neutral">
                            Conduction needs touching particles; convection needs a flowing fluid. Between the Sun and Earth there is
                            neither — just vacuum — yet the energy still arrives. <b>Infrared radiation</b> travels as a wave and
                            needs no material at all, which is why it is the only way heat crosses empty space.
                        </Flag>
                    </>
                )}
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, tempC })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
