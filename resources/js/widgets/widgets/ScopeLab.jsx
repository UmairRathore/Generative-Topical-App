import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: scope_lab ────────────────────────────────────────────────────────
// Bespoke 4.6 hero (immersive 2D). The cathode-ray oscilloscope (CRO).
//   DISPLAY — the CRO shows a p.d. plotted against time as a WAVEFORM; switch
//             between a steady d.c. level, an a.c. (sine) trace and a sound trace.
//   MEASURE — read a p.d. from the trace's height using the Y-GAIN (volts/division):
//             p.d. = height in divisions × V/div; and read a time / period from the
//             trace's width using the TIMEBASE (time/division): T = divisions ×
//             time/div, then f = 1/T.  (The CRO's internal structure is not shown.)
// config: { mode, ygain, timebase, waveform }

const YGAINS = [0.5, 1, 2, 5];      // V per division
const TIMEBASES = [1, 2, 5, 10];    // ms per division

export default function ScopeLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['display', 'measure'].includes(config.mode) ? config.mode : 'display');
    const [ygain, setYgain] = useState(YGAINS.includes(config.ygain) ? config.ygain : 2);
    const [timebase, setTimebase] = useState(TIMEBASES.includes(config.timebase) ? config.timebase : 2);
    const [waveform, setWaveform] = useState(['dc', 'ac', 'sound'].includes(config.waveform) ? config.waveform : 'ac');
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, ygain, timebase, waveform };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, ygain: st.current.ygain, timebase: st.current.timebase, waveform: st.current.waveform }),
            setState: (s) => {
                if (['display', 'measure'].includes(s?.mode)) setMode(s.mode);
                if (YGAINS.includes(s?.ygain)) setYgain(s.ygain);
                if (TIMEBASES.includes(s?.timebase)) setTimebase(s.timebase);
                if (['dc', 'ac', 'sound'].includes(s?.waveform)) setWaveform(s.waveform);
            },
        });
    }, [onReady]); // eslint-disable-line

    // MEASURE trace: fixed geometry — amplitude 2 divisions (peak), one period spans 4 divisions
    const AMP_DIV = 2, PERIOD_DIV = 4;
    const pkVolts = AMP_DIV * ygain;                 // peak p.d.
    const periodMs = PERIOD_DIV * timebase;          // period in ms
    const freqHz = 1000 / periodMs;                  // Hz

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, t0 = performance.now();
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
            bg.addColorStop(0, '#12161d'); bg.addColorStop(1, '#0b0e14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);
            ctx.textAlign = 'center';

            // ── the CRO screen: a grid of DIVISIONS ──────────────────────────────
            const gx = w * 0.06, gw = w * 0.62, gy = h * 0.10, gh = h * 0.72;
            const DIVX = 8, DIVY = 8, cellW = gw / DIVX, cellH = gh / DIVY;
            const midY = gy + gh / 2;
            // screen background (phosphor green tint)
            ctx.fillStyle = '#08120c'; ctx.fillRect(gx, gy, gw, gh);
            ctx.strokeStyle = 'rgba(80,160,110,.22)'; ctx.lineWidth = 1;
            for (let i = 0; i <= DIVX; i++) { ctx.beginPath(); ctx.moveTo(gx + i * cellW, gy); ctx.lineTo(gx + i * cellW, gy + gh); ctx.stroke(); }
            for (let j = 0; j <= DIVY; j++) { ctx.beginPath(); ctx.moveTo(gx, gy + j * cellH); ctx.lineTo(gx + gw, gy + j * cellH); ctx.stroke(); }
            // centre axes brighter
            ctx.strokeStyle = 'rgba(120,200,150,.5)'; ctx.lineWidth = 1.4;
            ctx.beginPath(); ctx.moveTo(gx, midY); ctx.lineTo(gx + gw, midY); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(gx + gw / 2, gy); ctx.lineTo(gx + gw / 2, gy + gh); ctx.stroke();
            ctx.strokeRect(gx, gy, gw, gh);

            // ── the trace (glowing green) ────────────────────────────────────────
            const trace = (fn) => {
                ctx.strokeStyle = '#39ff9a'; ctx.lineWidth = 2.4; ctx.shadowColor = '#39ff9a'; ctx.shadowBlur = 8;
                ctx.beginPath();
                for (let px = 0; px <= gw; px++) { const yy = midY - fn(px / cellW) * cellH; if (px === 0) ctx.moveTo(gx + px, yy); else ctx.lineTo(gx + px, yy); }
                ctx.stroke(); ctx.shadowBlur = 0;
            };

            if (S.mode === 'display') {
                if (S.waveform === 'dc') trace(() => 1.5);                                   // steady level (1.5 div up)
                else if (S.waveform === 'ac') trace((dv) => 2 * Math.sin((dv / 4) * Math.PI * 2));
                else trace((dv) => 1.5 * Math.sin((dv / 3) * Math.PI * 2) + 0.6 * Math.sin((dv / 1) * Math.PI * 2)); // richer 'sound' timbre
                ctx.fillStyle = faint; ctx.font = '600 10px Inter';
                const lbl = { dc: 'a STEADY d.c. voltage → a flat horizontal line', ac: 'an ALTERNATING (a.c.) voltage → a sine wave', sound: 'a SOUND signal (from a microphone) → its waveform' }[S.waveform];
                ctx.fillText(lbl, gx + gw / 2, gy + gh + 22);
                ctx.fillStyle = ink; ctx.font = '700 11px Inter';
                ctx.fillText('the oscilloscope shows the p.d. (vertical) against time (horizontal)', gx + gw / 2, gy + gh + 40);
            } else {
                // MEASURE: a fixed sine — amplitude 2 div, period 4 div
                trace((dv) => AMP_DIV * Math.sin((dv / PERIOD_DIV) * Math.PI * 2));
                // amplitude bracket (vertical, left side)
                const bx = gx + gw / 2 - 0.0 * cellW + cellW * 1.0;
                const peakY = midY - AMP_DIV * cellH;
                ctx.strokeStyle = amber; ctx.lineWidth = 1.6; ctx.setLineDash([4, 3]);
                ctx.beginPath(); ctx.moveTo(gx, peakY); ctx.lineTo(gx + gw, peakY); ctx.stroke(); ctx.setLineDash([]);
                ctx.strokeStyle = amber; ctx.beginPath(); ctx.moveTo(gx + 14, midY); ctx.lineTo(gx + 14, peakY); ctx.stroke();
                ctx.fillStyle = amber; ctx.font = '700 9px Inter'; ctx.textAlign = 'left';
                ctx.fillText(`${AMP_DIV} div`, gx + 18, (midY + peakY) / 2);
                // period bracket (horizontal, along top)
                const p0 = gx + cellW * 0.0, p1 = gx + PERIOD_DIV * cellW;
                ctx.strokeStyle = cyan; ctx.beginPath(); ctx.moveTo(p0, gy + 10); ctx.lineTo(p1, gy + 10); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(p0, gy + 6); ctx.lineTo(p0, gy + 14); ctx.moveTo(p1, gy + 6); ctx.lineTo(p1, gy + 14); ctx.stroke();
                ctx.fillStyle = cyan; ctx.textAlign = 'center'; ctx.fillText(`1 period = ${PERIOD_DIV} div`, (p0 + p1) / 2, gy + 24);
                ctx.textAlign = 'center';
                ctx.fillStyle = ink; ctx.font = '700 11px "JetBrains Mono", monospace';
                ctx.fillText(`peak p.d. = ${AMP_DIV} div × ${ygain} V/div = ${pkVolts} V`, gx + gw / 2, gy + gh + 22);
                ctx.fillText(`period T = ${PERIOD_DIV} div × ${timebase} ms/div = ${periodMs} ms → f = 1/T = ${freqHz.toFixed(freqHz < 100 ? 0 : 0)} Hz`, gx + gw / 2, gy + gh + 40);
            }

            // ── the two control knobs (Y-gain, timebase) ─────────────────────────
            const knob = (cx, cy, label, val, unit, col) => {
                ctx.strokeStyle = col; ctx.lineWidth = 2.2; ctx.beginPath(); ctx.arc(cx, cy, 22, 0, Math.PI * 2); ctx.stroke();
                const ang = -Math.PI * 0.7 + (Math.PI * 1.4) * 0.5;
                ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(cx + Math.cos(ang) * 16, cy + Math.sin(ang) * 16); ctx.stroke();
                ctx.fillStyle = ink; ctx.font = '700 10px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(`${val} ${unit}`, cx, cy + 38);
                ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.fillText(label, cx, cy - 30);
            };
            const kx = w * 0.84;
            knob(kx, h * 0.28, 'Y-GAIN', ygain, 'V/div', amber);
            knob(kx, h * 0.66, 'TIMEBASE', timebase, 'ms/div', cyan);

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{mode === 'display' ? 'Oscilloscope · displaying a waveform' : 'Oscilloscope · measuring p.d. and time'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'display' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('display')}>〜 Display</button>
                    <button className={'cw-btn ' + (mode === 'measure' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('measure')}>📏 Measure</button>
                </div>

                {mode === 'display' && (
                    <>
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (waveform === 'dc' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setWaveform('dc')}>d.c.</button>
                            <button className={'cw-btn ' + (waveform === 'ac' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setWaveform('ac')}>a.c.</button>
                            <button className={'cw-btn ' + (waveform === 'sound' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setWaveform('sound')}>sound</button>
                        </div>
                        <Stat label="the oscilloscope displays a waveform" value="p.d. vs time" tone="acc"
                              sub={<>a CRO draws a <b>trace</b> of the <b>p.d.</b> (up the screen) against <b>time</b> (across the screen). A <b>steady d.c.</b> gives a flat line; an <b>a.c.</b> gives a sine wave; a <b>sound</b> signal shows its shape.</>} />
                        <Flag kind="neutral">
                            An <b>oscilloscope (CRO)</b> displays how a <b>p.d. changes with time</b> as a glowing <b>trace</b> on its screen — the
                            <b> vertical</b> direction shows the <b>voltage</b> and the <b>horizontal</b> shows <b>time</b>. So a steady d.c. voltage
                            is a <b>flat horizontal line</b>, an alternating voltage is a <b>sine wave</b>, and a sound picked up by a microphone
                            appears as its <b>waveform</b>. (You do not need to know the CRO's internal structure.)
                        </Flag>
                    </>
                )}

                {mode === 'measure' && (
                    <>
                        <Stat label="reading the trace" value={`${pkVolts} V, ${periodMs} ms`} tone="acc"
                              sub={<><b>p.d. = height × Y-gain</b> = {AMP_DIV} div × {ygain} V/div = <b>{pkVolts} V</b>. <b>T = width × timebase</b> = {PERIOD_DIV} div × {timebase} ms/div = <b>{periodMs} ms</b>, so <b>f = 1/T = {freqHz.toFixed(0)} Hz</b>.</>} />
                        <Slider label="Y-gain (volts / division)" value={YGAINS.indexOf(ygain)} min={0} max={3} step={1} onChange={(i) => setYgain(YGAINS[i])} format={() => `${ygain} V/div`} />
                        <Slider label="timebase (time / division)" value={TIMEBASES.indexOf(timebase)} min={0} max={3} step={1} onChange={(i) => setTimebase(TIMEBASES[i])} format={() => `${timebase} ms/div`} />
                        <Flag kind="neutral">
                            To <b>measure a p.d.</b>: count how many <b>divisions</b> tall the trace is and multiply by the <b>Y-gain</b> (volts per
                            division). To <b>measure a short time</b> (or a period): count how many <b>divisions</b> wide it is and multiply by the
                            <b> timebase</b> (time per division). Here the wave is <b>{AMP_DIV} divisions</b> tall and one cycle is <b>{PERIOD_DIV}
                            divisions</b> wide, so <b>p.d. = {pkVolts} V</b> and <b>T = {periodMs} ms</b> (frequency <b>f = 1/T = {freqHz.toFixed(0)} Hz</b>).
                            Turning the Y-gain or timebase down (fewer V or ms per division) spreads the trace out for a more accurate reading.
                        </Flag>
                    </>
                )}

                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, ygain, timebase, waveform })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
