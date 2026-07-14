import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: sound_lab ────────────────────────────────────────────────────────
// Bespoke Sound hero (5054 · 3.4). Four modes:
//   WAVE   — a vibrating source drives a longitudinal wave through a particle
//            medium: the dots bunch into compressions and spread into
//            rarefactions; frequency and amplitude sliders.
//   SCOPE  — an oscilloscope trace: amplitude = loudness, frequency = pitch,
//            and a waveform-shape (timbre) selector for different sources.
//   VACUUM — a ringing bell under a jar; pump the air out and the sound fades to
//            silence (sound needs a medium).
//   SONAR  — a pulse travels to a reflecting surface and back; time it and get
//            the depth from distance = speed x time (halved for there-and-back).
//
// config: { mode, frequency, amplitude, timbre, air, depth }
// Notes: getState/setState carry the same keys.

const TIMBRES = ['pure', 'rich', 'reedy'];

export default function SoundLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['wave', 'scope', 'vacuum', 'sonar'].includes(config.mode) ? config.mode : 'wave');
    const [frequency, setFrequency] = useState(typeof config.frequency === 'number' ? config.frequency : 3);
    const [amplitude, setAmplitude] = useState(typeof config.amplitude === 'number' ? config.amplitude : 0.6);
    const [timbre, setTimbre] = useState(TIMBRES.includes(config.timbre) ? config.timbre : 'pure');
    const [air, setAir] = useState(typeof config.air === 'number' ? config.air : 1);
    const [depth, setDepth] = useState(typeof config.depth === 'number' ? config.depth : 600);
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, frequency, amplitude, timbre, air, depth };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, frequency: st.current.frequency, amplitude: st.current.amplitude, timbre: st.current.timbre, air: st.current.air, depth: st.current.depth }),
            setState: (s) => {
                if (['wave', 'scope', 'vacuum', 'sonar'].includes(s?.mode)) setMode(s.mode);
                if (typeof s?.frequency === 'number') setFrequency(Math.max(1, Math.min(8, s.frequency)));
                if (typeof s?.amplitude === 'number') setAmplitude(Math.max(0.15, Math.min(1, s.amplitude)));
                if (TIMBRES.includes(s?.timbre)) setTimbre(s.timbre);
                if (typeof s?.air === 'number') setAir(Math.max(0, Math.min(1, s.air)));
                if (typeof s?.depth === 'number') setDepth(Math.max(150, Math.min(1500, s.depth)));
            },
        });
    }, [onReady]); // eslint-disable-line

    const V_WATER = 1500; // m/s, for the sonar calc
    const sonarT = (2 * depth / V_WATER); // s (there and back)

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, t0 = performance.now();

        const shape = (ph, tb) => {
            if (tb === 'pure') return Math.sin(ph);
            if (tb === 'rich') return (Math.sin(ph) + 0.4 * Math.sin(2 * ph) + 0.22 * Math.sin(3 * ph)) / 1.5;
            return (Math.sin(ph) + 0.5 * Math.sin(3 * ph) + 0.3 * Math.sin(5 * ph)) / 1.6; // reedy (odd harmonics)
        };

        const draw = (now) => {
            const amb = (now - t0) / 1000;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight;
            if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', cyan = '#38BDF8', rose = '#FB7185';
            const S = st.current;
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#0f141c'); bg.addColorStop(1, '#0a0e14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);

            if (S.mode === 'wave') {
                const cy = h * 0.5, cols = 46, rows = 5;
                const x0 = w * 0.16, x1 = w * 0.96;
                const dx = (x1 - x0) / cols;
                const k = S.frequency * 0.9;                 // spatial frequency
                const A = S.amplitude * dx * 2.4;            // displacement amplitude
                const om = 3.2;
                // vibrating source (speaker cone) on the left
                const cone = Math.sin(-amb * om) * A;
                ctx.fillStyle = 'rgba(120,150,180,.5)';
                ctx.beginPath(); ctx.moveTo(w * 0.05, cy - 34); ctx.lineTo(w * 0.11 + cone, cy - 22); ctx.lineTo(w * 0.11 + cone, cy + 22); ctx.lineTo(w * 0.05, cy + 34); ctx.closePath(); ctx.fill();
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('vibrating', w * 0.075, cy - 40); ctx.fillText('source', w * 0.075, cy + 50);
                // medium particles with longitudinal displacement
                let minGrad = 1e9, compX = x0, maxGrad = -1e9, rareX = x0;
                for (let c = 0; c < cols; c++) {
                    const xe = x0 + c * dx;
                    const s = A * Math.sin(k * (c * dx / dx) - amb * om);
                    const grad = Math.cos(k * (c) - amb * om);   // d(displacement) proxy
                    if (grad < minGrad) { minGrad = grad; compX = xe + s; }
                    if (grad > maxGrad) { maxGrad = grad; rareX = xe + s; }
                    for (let r = 0; r < rows; r++) {
                        const ye = cy + (r - (rows - 1) / 2) * (h * 0.13);
                        ctx.fillStyle = 'rgba(150,210,235,.85)';
                        ctx.beginPath(); ctx.arc(xe + s, ye, 2.7, 0, Math.PI * 2); ctx.fill();
                    }
                }
                ctx.font = '700 10px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillStyle = amber; ctx.fillText('compression', compX, cy - h * 0.34);
                ctx.strokeStyle = amber; ctx.setLineDash([3, 3]); ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(compX, cy - h * 0.30); ctx.lineTo(compX, cy + h * 0.30); ctx.stroke(); ctx.setLineDash([]);
                ctx.fillStyle = cyan; ctx.fillText('rarefaction', rareX, cy + h * 0.40);
                ctx.strokeStyle = cyan; ctx.setLineDash([3, 3]); ctx.beginPath(); ctx.moveTo(rareX, cy - h * 0.30); ctx.lineTo(rareX, cy + h * 0.30); ctx.stroke(); ctx.setLineDash([]);
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif';
                ctx.fillText('a longitudinal wave: particles vibrate along the direction of travel →', w * 0.5, h - 12);
            } else if (S.mode === 'scope') {
                // oscilloscope screen
                const mx = w * 0.06, my = h * 0.12, sw = w * 0.88, sh = h * 0.62;
                ctx.fillStyle = '#08150f'; ctx.fillRect(mx, my, sw, sh);
                ctx.strokeStyle = 'rgba(52,211,153,.18)'; ctx.lineWidth = 1;
                for (let i = 1; i < 8; i++) { ctx.beginPath(); ctx.moveTo(mx + sw * i / 8, my); ctx.lineTo(mx + sw * i / 8, my + sh); ctx.stroke(); }
                for (let i = 1; i < 4; i++) { ctx.beginPath(); ctx.moveTo(mx, my + sh * i / 4); ctx.lineTo(mx + sw, my + sh * i / 4); ctx.stroke(); }
                const midY = my + sh / 2, A = S.amplitude * sh * 0.42, cycles = S.frequency;
                ctx.strokeStyle = acc; ctx.lineWidth = 2.2; ctx.beginPath();
                for (let px = 0; px <= sw; px += 2) {
                    const ph = (px / sw) * cycles * 2 * Math.PI - amb * 2.2;
                    const y = midY - A * shape(ph, S.timbre);
                    px === 0 ? ctx.moveTo(mx + px, y) : ctx.lineTo(mx + px, y);
                }
                ctx.stroke();
                // amplitude / period markers
                ctx.strokeStyle = amber; ctx.lineWidth = 1.4; ctx.setLineDash([4, 3]);
                ctx.beginPath(); ctx.moveTo(mx + 18, midY); ctx.lineTo(mx + 18, midY - A); ctx.stroke(); ctx.setLineDash([]);
                ctx.fillStyle = amber; ctx.font = '700 9px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText('amplitude', mx + 22, midY - A / 2);
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('taller = louder   ·   more cycles = higher pitch   ·   shape = timbre', w * 0.5, h - 12);
            } else if (S.mode === 'vacuum') {
                const cx = w * 0.5, jarTop = h * 0.14, jarBot = h * 0.78, jarW = Math.min(w * 0.42, h * 0.6);
                // sound waves radiating from the bell — opacity scales with air
                const airAmt = S.air;
                for (let n = 0; n < 4; n++) {
                    const rr = ((amb * 40 + n * 26) % 120) + 10;
                    ctx.strokeStyle = `rgba(52,211,153,${0.35 * airAmt * (1 - rr / 130)})`;
                    ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(cx, (jarTop + jarBot) / 2, rr, 0, Math.PI * 2); ctx.stroke();
                }
                // air particles inside
                const nDots = Math.round(airAmt * 60);
                ctx.fillStyle = 'rgba(150,190,220,.5)';
                for (let i = 0; i < nDots; i++) {
                    const a = (i * 2.4 + amb) % (Math.PI * 2), rr = (i * 37 % (jarW * 0.42));
                    ctx.beginPath(); ctx.arc(cx + Math.cos(a) * rr, (jarTop + jarBot) / 2 + Math.sin(a * 1.3) * rr * 0.7, 1.6, 0, Math.PI * 2); ctx.fill();
                }
                // the bell (ringing clapper)
                const swing = Math.sin(amb * 12) * 5;
                ctx.fillStyle = 'rgba(200,170,90,.85)';
                ctx.beginPath(); ctx.moveTo(cx - 16, (jarTop + jarBot) / 2 + 18); ctx.quadraticCurveTo(cx, (jarTop + jarBot) / 2 - 20, cx + 16, (jarTop + jarBot) / 2 + 18); ctx.closePath(); ctx.fill();
                ctx.fillStyle = '#caa85a'; ctx.beginPath(); ctx.arc(cx + swing, (jarTop + jarBot) / 2 + 24, 3.4, 0, Math.PI * 2); ctx.fill();
                // jar (bell-jar dome)
                ctx.strokeStyle = 'rgba(200,225,245,.55)'; ctx.lineWidth = 2.4;
                ctx.beginPath(); ctx.moveTo(cx - jarW / 2, jarBot); ctx.lineTo(cx - jarW / 2, jarTop + 40);
                ctx.quadraticCurveTo(cx - jarW / 2, jarTop, cx, jarTop); ctx.quadraticCurveTo(cx + jarW / 2, jarTop, cx + jarW / 2, jarTop + 40);
                ctx.lineTo(cx + jarW / 2, jarBot); ctx.stroke();
                ctx.strokeStyle = 'rgba(160,180,200,.7)'; ctx.lineWidth = 5; ctx.beginPath(); ctx.moveTo(cx - jarW * 0.6, jarBot); ctx.lineTo(cx + jarW * 0.6, jarBot); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'right';
                ctx.fillText('to vacuum pump →', cx + jarW * 0.6, jarBot + 16);
                ctx.textAlign = 'center';
                ctx.fillStyle = airAmt < 0.06 ? rose : acc; ctx.font = '700 12px Inter, sans-serif';
                ctx.fillText(airAmt < 0.06 ? 'vacuum — the bell still moves, but NO sound reaches you' : `air present (${Math.round(airAmt * 100)}%) — sound travels out through it`, w * 0.5, h - 12);
            } else {
                // sonar
                const cx = w * 0.5, top = h * 0.16, bot = h * 0.82;
                const dFrac = (S.depth - 150) / (1500 - 150);
                const surfaceY = top + 26 + dFrac * (bot - top - 40);
                // water
                ctx.fillStyle = 'rgba(30,90,140,.18)'; ctx.fillRect(w * 0.1, top + 18, w * 0.8, surfaceY - top - 18);
                // boat
                ctx.fillStyle = 'rgba(200,210,220,.85)';
                ctx.beginPath(); ctx.moveTo(cx - 26, top + 8); ctx.lineTo(cx + 26, top + 8); ctx.lineTo(cx + 16, top + 20); ctx.lineTo(cx - 16, top + 20); ctx.closePath(); ctx.fill();
                // seabed
                ctx.fillStyle = 'rgba(120,95,60,.7)'; ctx.fillRect(w * 0.1, surfaceY, w * 0.8, bot - surfaceY);
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText('seabed', w * 0.11, surfaceY + 14);
                // pulse: down then up, looping
                const travel = surfaceY - (top + 20);
                const cyc = (amb * 90) % (2 * travel);
                const goingDown = cyc < travel;
                const py = goingDown ? (top + 20) + cyc : surfaceY - (cyc - travel);
                ctx.fillStyle = goingDown ? '#fff2c0' : '#7be0ff';
                ctx.beginPath(); ctx.arc(cx, py, 5, 0, Math.PI * 2); ctx.fill();
                ctx.strokeStyle = goingDown ? 'rgba(255,242,192,.4)' : 'rgba(123,224,255,.4)'; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.moveTo(cx, top + 20); ctx.lineTo(cx, py); ctx.stroke();
                ctx.textAlign = 'center'; ctx.fillStyle = ink; ctx.font = '600 10px "JetBrains Mono", monospace';
                ctx.fillText(`depth = ½ × v × t = ½ × 1500 × ${sonarT.toFixed(2)} = ${S.depth} m`, w * 0.5, h - 12);
                ctx.fillStyle = faint; ctx.fillText('pulse down (yellow) → echo back up (blue)', w * 0.5, top + 4);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const badge = { wave: 'Longitudinal wave · compressions & rarefactions', scope: 'Oscilloscope · loudness, pitch & timbre', vacuum: 'Bell jar · sound needs a medium', sonar: 'Sonar · echo & depth' }[mode];

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{badge}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'wave' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('wave')}>〰 Wave</button>
                    <button className={'cw-btn ' + (mode === 'scope' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('scope')}>📟 Scope</button>
                    <button className={'cw-btn ' + (mode === 'vacuum' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('vacuum')}>🔔 Bell jar</button>
                    <button className={'cw-btn ' + (mode === 'sonar' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('sonar')}>🌊 Sonar</button>
                </div>

                {mode === 'wave' && (
                    <>
                        <Stat label="sound is a longitudinal wave" value="compressions & rarefactions" tone="acc"
                              sub={<>a vibrating source pushes the particles of the medium back and forth <b>along</b> the direction of travel, bunching them into <b>compressions</b> and spreading them into <b>rarefactions</b></>} />
                        <Slider label="frequency (→ pitch)" value={frequency} min={1} max={8} step={1} onChange={setFrequency} format={(x) => `${x} (relative)`} />
                        <Slider label="amplitude (→ loudness)" value={amplitude} min={0.15} max={1} step={0.05} onChange={setAmplitude} format={(x) => `${Math.round(x * 100)}%`} />
                        <Flag kind="neutral">
                            Sound is made by a <b>vibrating source</b> (a speaker cone, a string, your vocal cords). In a
                            <b> longitudinal</b> wave the particles vibrate <b>along</b> the direction the wave travels — unlike a
                            transverse wave, where they move across it. The bunched-up regions are <b>compressions</b>, the
                            stretched-out regions <b>rarefactions</b>.
                        </Flag>
                    </>
                )}

                {mode === 'scope' && (
                    <>
                        <Stat label="reading a sound on an oscilloscope" value="amplitude=loudness · freq=pitch" tone="acc"
                              sub={<><b>Amplitude</b> sets the <b>loudness</b>; <b>frequency</b> sets the <b>pitch</b>; the <b>shape</b> of one cycle is the <b>timbre</b> that tells two instruments apart</>} />
                        <Slider label="amplitude (loudness)" value={amplitude} min={0.15} max={1} step={0.05} onChange={setAmplitude} format={(x) => `${Math.round(x * 100)}%`} />
                        <Slider label="frequency (pitch)" value={frequency} min={1} max={8} step={1} onChange={setFrequency} format={(x) => `${x} cycles`} />
                        <div className="cw-btnrow">
                            {TIMBRES.map((t) => <button key={t} className={'cw-btn ' + (timbre === t ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setTimbre(t)}>{t}</button>)}
                        </div>
                        <Flag kind="neutral">
                            A <b>bigger amplitude</b> (taller trace) is a <b>louder</b> sound; a <b>higher frequency</b> (more cycles
                            across the screen) is a <b>higher-pitched</b> sound. Two sources playing the same note look different
                            because their <b>waveform shape</b> differs — that shape is the <b>timbre</b> (quality).
                        </Flag>
                    </>
                )}

                {mode === 'vacuum' && (
                    <>
                        <Stat label="does sound need a medium?" value={air < 0.06 ? 'vacuum → silence' : 'air → sound travels'} tone={air < 0.06 ? 'warn' : 'acc'}
                              sub={<>a ringing bell hangs inside a jar; as the <b>air is pumped out</b> the sound fades — with no particles to pass the vibration on, <b>no sound reaches you</b></>} />
                        <Slider label="air in the jar (pump it out)" value={air} min={0} max={1} step={0.05} onChange={setAir} format={(x) => `${Math.round(x * 100)}%`} />
                        <Flag kind="neutral">
                            Sound is carried by the <b>particles of a medium</b> passing the vibration on. Pump the air out of the
                            jar and the bell still visibly rings, but the sound gets <b>quieter and quieter until you hear
                            nothing</b> — because a <b>vacuum has no particles</b> to carry it. Sound <b>cannot travel through a
                            vacuum</b>.
                        </Flag>
                    </>
                )}

                {mode === 'sonar' && (
                    <>
                        <Stat label="sonar: depth from an echo" value={`${depth} m`} tone="acc"
                              sub={<>a pulse takes <b>t = {sonarT.toFixed(2)} s</b> to travel down and back at v = 1500 m/s in water; the depth is <b>half</b> of v × t, because the pulse covers the distance <b>twice</b></>} />
                        <Slider label="sea depth" value={depth} min={150} max={1500} step={50} onChange={setDepth} format={(x) => `${x} m`} />
                        <Flag kind="neutral">
                            An <b>echo</b> is sound <b>reflected</b> off a surface. In <b>sonar</b> a ship sends a sound (or
                            ultrasound) pulse down and times how long the echo takes to return. The pulse travels the depth
                            <b> twice</b> (down and back), so <b>depth = ½ × speed × time</b>. The same distance = speed × time idea
                            lets you measure the <b>speed of sound in air</b> from a distant echo.
                        </Flag>
                    </>
                )}

                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, frequency, amplitude, timbre, air, depth })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
