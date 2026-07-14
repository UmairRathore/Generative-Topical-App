import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: power_elec_lab ───────────────────────────────────────────────────
// Bespoke 4.4.1 hero (immersive 2D). Electrical power, energy and cost.
//   POWER  — an appliance on the 230 V mains; the current it draws and the power
//            P = I V shown live as you pick different appliances.
//   ENERGY — the same appliance over time: energy E = I V t = P t accumulates
//            (in joules and in kilowatt-hours) as a clock runs.
//   COST   — the kilowatt-hour as the unit of energy sold: cost = power(kW) ×
//            time(h) × price-per-kWh, with a live cost meter.
// config: { mode, appliance, hours, price }

const MAINS = 230; // V
const APPLIANCES = [
    { key: 'lamp', name: 'LED lamp', P: 10 },
    { key: 'tv', name: 'television', P: 100 },
    { key: 'fridge', name: 'fridge', P: 200 },
    { key: 'heater', name: 'heater', P: 2000 },
    { key: 'kettle', name: 'kettle', P: 3000 },
];

export default function PowerElecLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['power', 'energy', 'cost'].includes(config.mode) ? config.mode : 'power');
    const [appIdx, setAppIdx] = useState(() => { const i = APPLIANCES.findIndex(a => a.key === config.appliance); return i >= 0 ? i : 3; });
    const [hours, setHours] = useState(typeof config.hours === 'number' ? config.hours : 2);
    const [price, setPrice] = useState(typeof config.price === 'number' ? config.price : 30); // pence per kWh
    const [tsec, setTsec] = useState(0); // energy-mode running clock (s)
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, appIdx, hours, price, tsec };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, appliance: APPLIANCES[st.current.appIdx].key, hours: st.current.hours, price: st.current.price }),
            setState: (s) => {
                if (['power', 'energy', 'cost'].includes(s?.mode)) setMode(s.mode);
                const i = APPLIANCES.findIndex(a => a.key === s?.appliance); if (i >= 0) setAppIdx(i);
                if (typeof s?.hours === 'number') setHours(Math.max(0.5, Math.min(10, s.hours)));
                if (typeof s?.price === 'number') setPrice(Math.max(5, Math.min(60, s.price)));
            },
        });
    }, [onReady]); // eslint-disable-line

    const app = APPLIANCES[appIdx];
    const P = app.P, I = P / MAINS;         // A
    const kW = P / 1000;
    const E_j = P * tsec;                    // J at the current clock
    const E_kwh = kW * hours;                // kWh over the chosen hours
    const cost = kW * hours * price;         // pence

    // energy-mode clock: advance tsec up to `hours` worth (scaled: 1 real s = 1 model min)
    useEffect(() => {
        if (mode !== 'energy') return undefined;
        let raf, last = performance.now();
        const tick = (now) => {
            const dt = (now - last) / 1000; last = now;
            setTsec((t) => Math.min(hours * 3600, t + dt * 600)); // 600× so a couple of hours plays out
            raf = requestAnimationFrame(tick);
        };
        raf = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(raf);
    }, [mode, hours]);
    useEffect(() => { setTsec(0); }, [mode, appIdx, hours]);

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf;
        const draw = () => {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight;
            if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', cyan = '#38BDF8';
            const S = st.current, a = APPLIANCES[S.appIdx];
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#12161d'); bg.addColorStop(1, '#0b0e14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);
            const wire = 'rgba(200,210,225,.85)';
            ctx.lineCap = 'round'; ctx.lineJoin = 'round';

            // appliance box + a simple mains loop (all modes)
            const ax = w * 0.5, ay = h * 0.34, aw = 120, ah = 64;
            const L = w * 0.14, R = w * 0.86, T = h * 0.16, B = h * 0.56;
            ctx.strokeStyle = wire; ctx.lineWidth = 2.2;
            ctx.strokeRect(L, T, R - L, B - T);
            // appliance
            const glow = a.P >= 2000 ? amber : a.P >= 100 ? cyan : acc;
            ctx.fillStyle = 'rgba(255,255,255,.04)'; ctx.strokeStyle = glow; ctx.lineWidth = 2.4;
            ctx.beginPath(); ctx.rect(ax - aw / 2, ay - ah / 2, aw, ah); ctx.fill(); ctx.stroke();
            ctx.fillStyle = glow; ctx.font = '700 13px Inter'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillText(a.name, ax, ay - 6);
            ctx.fillStyle = ink; ctx.font = '700 12px "JetBrains Mono", monospace';
            ctx.fillText(`${a.P} W`, ax, ay + 14); ctx.textBaseline = 'alphabetic';
            // cell/mains on left
            ctx.fillStyle = faint; ctx.font = '600 10px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            ctx.fillText('230 V', L, (T + B) / 2 - 6);
            ctx.lineWidth = 2; ctx.strokeStyle = wire; ctx.beginPath(); ctx.moveTo(L - 6, (T + B) / 2 - 10); ctx.lineTo(L + 6, (T + B) / 2 - 10); ctx.stroke(); ctx.lineWidth = 5; ctx.beginPath(); ctx.moveTo(L - 3, (T + B) / 2 + 8); ctx.lineTo(L + 3, (T + B) / 2 + 8); ctx.stroke();

            const bigReadout = (line1, line2, sub) => {
                ctx.textAlign = 'center';
                ctx.fillStyle = acc; ctx.font = '800 22px "JetBrains Mono", monospace'; ctx.fillText(line1, w * 0.5, B + 52);
                ctx.fillStyle = ink; ctx.font = '700 13px "JetBrains Mono", monospace'; ctx.fillText(line2, w * 0.5, B + 78);
                if (sub) { ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.fillText(sub, w * 0.5, h - 12); }
            };

            if (S.mode === 'power') {
                const I2 = a.P / MAINS;
                bigReadout(`P = I × V = ${I2.toFixed(1)} × 230 = ${a.P} W`, `current drawn I = P/V = ${I2.toFixed(1)} A`,
                    'power is the energy transferred per second; a hotter/brighter appliance draws more current');
            } else if (S.mode === 'energy') {
                const Ej = a.P * S.tsec;
                const mins = S.tsec / 60;
                bigReadout(`E = P × t = ${a.P} × ${Math.round(S.tsec)} s = ${Math.round(Ej).toLocaleString()} J`,
                    `= ${(Ej / 3.6e6).toFixed(3)} kW h   after ${mins.toFixed(0)} min`,
                    'E = I V t = P t : energy piles up the longer it runs (1 kW h = 3 600 000 J)');
                // energy bar
                const frac = Math.min(1, S.tsec / (S.hours * 3600));
                ctx.fillStyle = 'rgba(255,255,255,.08)'; ctx.fillRect(w * 0.2, B + 92, w * 0.6, 10);
                ctx.fillStyle = amber; ctx.fillRect(w * 0.2, B + 92, w * 0.6 * frac, 10);
            } else {
                const kw = a.P / 1000, c = kw * S.hours * S.price;
                bigReadout(`cost = ${kw.toFixed(2)} kW × ${S.hours} h × ${S.price}p = ${Math.round(c)}p`,
                    `energy used = ${(kw * S.hours).toFixed(2)} kW h   (£${(c / 100).toFixed(2)})`,
                    '1 kilowatt-hour = energy used by a 1 kW appliance in 1 hour; you pay per kW h');
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const badge = { power: 'Power · P = I V', energy: 'Energy · E = I V t', cost: 'Cost · kilowatt-hour' }[mode];

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
                    <button className={'cw-btn ' + (mode === 'power' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('power')}>⚡ Power</button>
                    <button className={'cw-btn ' + (mode === 'energy' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('energy')}>⏱ Energy</button>
                    <button className={'cw-btn ' + (mode === 'cost' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('cost')}>£ Cost</button>
                </div>
                <div className="cw-btnrow">
                    {APPLIANCES.map((a, i) => (
                        <button key={a.key} className={'cw-btn ' + (appIdx === i ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setAppIdx(i)}>{a.name}</button>
                    ))}
                </div>

                {mode === 'power' && (
                    <>
                        <Stat label={`${app.name} · power`} value={`${P} W`} tone="acc"
                              sub={<>on the 230 V mains it draws <b>I = P/V = {I.toFixed(1)} A</b>. Power <b>P = I V</b> is the energy it transfers <b>each second</b>. A hotter/brighter appliance = more power = more current.</>} />
                        <Flag kind="neutral">
                            <b>Electricity has many uses</b> — heating, lighting, charging batteries, and powering motors and electronic systems.
                            The <b>power</b> of an appliance is the energy it transfers each second: <b>P = I V</b> (watts = amps × volts). Pick a bigger
                            appliance and both its power and the current it draws go up.
                        </Flag>
                    </>
                )}

                {mode === 'energy' && (
                    <>
                        <Stat label={`${app.name} · energy so far`} value={`${(E_j / 3.6e6).toFixed(3)} kW h`} tone="acc"
                              sub={<>energy <b>E = I V t = P t</b> keeps piling up the longer it runs. {Math.round(E_j).toLocaleString()} J = {(E_j / 3.6e6).toFixed(3)} kW h (<b>1 kW h = 3 600 000 J</b>).</>} />
                        <Slider label="run for" value={hours} min={0.5} max={10} step={0.5} onChange={setHours} format={(x) => `${x} h`} />
                        <Flag kind="neutral">
                            <b>Energy = power × time:</b> <b>E = I V t = P t</b>. The joule is small, so mains energy is often given in
                            <b> kilowatt-hours</b>: the energy a <b>1 kW</b> appliance uses in <b>1 hour</b> (= 3.6 million J). Watch the energy
                            accumulate as the clock runs — the same appliance uses <b>more energy the longer it is on</b>.
                        </Flag>
                    </>
                )}

                {mode === 'cost' && (
                    <>
                        <Stat label={`${app.name} · cost to run`} value={`${Math.round(cost)}p  (£${(cost / 100).toFixed(2)})`} tone="acc"
                              sub={<>cost = <b>power(kW) × time(h) × price</b> = {kW.toFixed(2)} × {hours} × {price}p. Energy used = <b>{E_kwh.toFixed(2)} kW h</b>.</>} />
                        <Slider label="time on" value={hours} min={0.5} max={10} step={0.5} onChange={setHours} format={(x) => `${x} h`} />
                        <Slider label="price per kW h" value={price} min={5} max={60} step={1} onChange={setPrice} format={(x) => `${x}p`} />
                        <Flag kind="neutral">
                            The <b>kilowatt-hour (kW h)</b> is the <b>unit of energy the electricity company sells</b>: the energy used by a
                            <b> 1 kW</b> appliance in <b>1 hour</b>. To find the cost: <b>cost = number of kW h × price per kW h</b>, where
                            <b> number of kW h = power in kW × time in hours</b>. A big appliance (kettle, heater) costs far more per hour than a small one (lamp).
                        </Flag>
                    </>
                )}

                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, appliance: app.key, hours, price })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
