import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: sensor_lab ───────────────────────────────────────────────────────
// Bespoke 4.3.3 hero (immersive 2D). Input sensors + the potential divider.
//   SENSOR  — a thermistor (NTC) or an LDR whose resistance FALLS as the
//             temperature / light rises. A stimulus slider drives it; the
//             resistance and a live R-vs-stimulus curve respond.
//   DIVIDER — a variable potential divider: two resistors in series across a
//             supply, output taken across the lower one. A wiper slider changes
//             the split; V_out and the ratio R1/R2 = V1/V2 update live.
//   CIRCUIT — the sensor AS one resistor of the divider (an input sensor): as
//             the stimulus changes, the sensor's resistance changes and the
//             output p.d. swings — the basis of a temperature / light switch.
// config: { mode, stimulus, ratio, sensor }

const VS = 12; // supply p.d. for the divider (volts)

// thermistor/LDR resistance (kΩ) as a decreasing function of stimulus 0..100
function sensorR(stimulus, sensor) {
    // cold/dark -> high R (~20 kΩ), hot/bright -> low R (~0.5 kΩ); smooth decay
    const t = Math.max(0, Math.min(100, stimulus)) / 100;
    return 0.5 + 19.5 * Math.pow(1 - t, 2.2);
}

export default function SensorLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['sensor', 'divider', 'circuit'].includes(config.mode) ? config.mode : 'sensor');
    const [stimulus, setStimulus] = useState(typeof config.stimulus === 'number' ? config.stimulus : 20);
    const [ratio, setRatio] = useState(typeof config.ratio === 'number' ? config.ratio : 50); // wiper %, = R2 share
    const [sensor, setSensor] = useState(config.sensor === 'ldr' ? 'ldr' : 'thermistor');
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, stimulus, ratio, sensor };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, stimulus: st.current.stimulus, ratio: st.current.ratio, sensor: st.current.sensor }),
            setState: (s) => {
                if (['sensor', 'divider', 'circuit'].includes(s?.mode)) setMode(s.mode);
                if (typeof s?.stimulus === 'number') setStimulus(Math.max(0, Math.min(100, s.stimulus)));
                if (typeof s?.ratio === 'number') setRatio(Math.max(5, Math.min(95, s.ratio)));
                if (s?.sensor === 'ldr' || s?.sensor === 'thermistor') setSensor(s.sensor);
            },
        });
    }, [onReady]); // eslint-disable-line

    // DIVIDER: two plain resistors. R2 share = ratio%, R1 share = (100-ratio)%.
    const Rtot = 10;
    const R2 = Rtot * ratio / 100, R1 = Rtot - R2;
    const Vout_div = VS * R2 / (R1 + R2), V1_div = VS - Vout_div;
    // CIRCUIT: sensor is R_top; fixed R_bottom = 5 kΩ; output across the bottom.
    const Rsen = sensorR(stimulus, sensor), Rfix = 5;
    const Vout_ckt = VS * Rfix / (Rsen + Rfix);

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
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', cyan = '#38BDF8', rose = '#FB7185';
            const S = st.current;
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#12161d'); bg.addColorStop(1, '#0b0e14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);
            const wire = 'rgba(200,210,225,.85)';
            ctx.lineCap = 'round'; ctx.lineJoin = 'round';

            if (S.mode === 'sensor') {
                const R = sensorR(S.stimulus, S.sensor), t = S.stimulus / 100;
                const isTh = S.sensor === 'thermistor';
                // left: the sensor symbol + stimulus gauge
                const sx = w * 0.28, sy = h * 0.42;
                ctx.strokeStyle = wire; ctx.lineWidth = 2;
                ctx.strokeRect(sx - 30, sy - 14, 60, 28);
                if (isTh) { ctx.beginPath(); ctx.moveTo(sx - 40, sy + 26); ctx.lineTo(sx - 18, sy + 26); ctx.lineTo(sx + 40, sy - 26); ctx.stroke(); }
                else { ctx.beginPath(); ctx.arc(sx, sy, 34, 0, Math.PI * 2); ctx.stroke(); ctx.strokeStyle = amber; for (let i = 0; i < 2; i++) { const ox = sx + 4 + i * 12; ctx.beginPath(); ctx.moveTo(ox + 20, sy - 40); ctx.lineTo(ox + 4, sy - 22); ctx.stroke(); ctx.beginPath(); ctx.moveTo(ox + 4, sy - 22); ctx.lineTo(ox + 10, sy - 24); ctx.moveTo(ox + 4, sy - 22); ctx.lineTo(ox + 7, sy - 28); ctx.stroke(); } }
                // stimulus bar
                const bx = sx - 30, by = h * 0.74, bw = 60, bh = 12;
                ctx.fillStyle = 'rgba(255,255,255,.08)'; ctx.fillRect(bx, by, bw, bh);
                ctx.fillStyle = isTh ? rose : amber; ctx.fillRect(bx, by, bw * t, bh);
                ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.textAlign = 'center';
                ctx.fillText(isTh ? `temperature ${Math.round(S.stimulus)} %` : `light ${Math.round(S.stimulus)} %`, sx, by + 26);
                ctx.fillStyle = ink; ctx.font = '700 12px "JetBrains Mono", monospace';
                ctx.fillText(isTh ? 'NTC thermistor' : 'LDR', sx, sy - 40);
                // right: R vs stimulus curve
                const gx = w * 0.56, gy = h * 0.2, gw = w * 0.36, gh = h * 0.56;
                ctx.strokeStyle = faint; ctx.lineWidth = 1;
                ctx.beginPath(); ctx.moveTo(gx, gy); ctx.lineTo(gx, gy + gh); ctx.lineTo(gx + gw, gy + gh); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.textAlign = 'left';
                ctx.fillText('R', gx - 12, gy + 6); ctx.textAlign = 'right'; ctx.fillText(isTh ? 'temp →' : 'light →', gx + gw, gy + gh + 14);
                ctx.strokeStyle = acc; ctx.lineWidth = 2.4; ctx.beginPath();
                for (let i = 0; i <= 60; i++) { const s = i / 60; const rr = sensorR(s * 100, S.sensor); const px = gx + s * gw; const py = gy + gh - (rr / 20) * gh; if (i === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py); }
                ctx.stroke();
                const px = gx + t * gw, py = gy + gh - (R / 20) * gh;
                ctx.fillStyle = amber; ctx.beginPath(); ctx.arc(px, py, 4.5, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = ink; ctx.font = '700 13px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(`R = ${R.toFixed(1)} kΩ`, gx + gw * 0.5, gy - 4);
                ctx.fillStyle = faint; ctx.font = '600 10px Inter';
                ctx.fillText(isTh ? 'NTC thermistor: hotter → LESS resistance' : 'LDR: brighter → LESS resistance', w * 0.5, h - 10);
            } else {
                // DIVIDER / CIRCUIT — a vertical potential divider
                const topR = S.mode === 'circuit' ? Rsen : R1;
                const botR = S.mode === 'circuit' ? Rfix : R2;
                const Vout = S.mode === 'circuit' ? Vout_ckt : Vout_div;
                const V1 = VS - Vout;
                const cx = w * 0.36, top = h * 0.14, bot = h * 0.86, midY = (top + bot) / 2;
                ctx.strokeStyle = wire; ctx.lineWidth = 2.2;
                // supply rail on the left
                ctx.beginPath(); ctx.moveTo(cx, top); ctx.lineTo(cx, bot); ctx.stroke();
                // cell at far left
                const clx = w * 0.14;
                ctx.beginPath(); ctx.moveTo(clx, top); ctx.lineTo(cx, top); ctx.moveTo(clx, bot); ctx.lineTo(cx, bot); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(clx, top); ctx.lineTo(clx, bot); ctx.stroke();
                ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(clx - 7, midY - 12); ctx.lineTo(clx + 7, midY - 12); ctx.stroke(); ctx.lineWidth = 5; ctx.beginPath(); ctx.moveTo(clx - 4, midY + 12); ctx.lineTo(clx + 4, midY + 12); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 10px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(`${VS} V`, clx, midY + 2);
                // top resistor box
                const boxTop = top + (midY - top) * 0.3;
                ctx.strokeStyle = S.mode === 'circuit' ? acc : wire; ctx.lineWidth = 2;
                ctx.strokeRect(cx - 16, boxTop, 32, 40);
                if (S.mode === 'circuit') { ctx.beginPath(); ctx.moveTo(cx - 22, boxTop + 52); ctx.lineTo(cx - 4, boxTop + 52); ctx.lineTo(cx + 24, boxTop - 8); ctx.stroke(); }
                ctx.fillStyle = ink; ctx.font = '700 11px "JetBrains Mono", monospace'; ctx.textAlign = 'left';
                ctx.fillText(`R1 = ${topR.toFixed(1)}${S.mode === 'circuit' ? ' kΩ' : ' Ω'}`, cx + 30, boxTop + 16);
                ctx.fillStyle = amber; ctx.fillText(`V1 = ${V1.toFixed(1)} V`, cx + 30, boxTop + 34);
                // bottom resistor box
                const boxBot = midY + (bot - midY) * 0.3;
                ctx.strokeStyle = wire; ctx.lineWidth = 2; ctx.strokeRect(cx - 16, boxBot, 32, 40);
                ctx.fillStyle = ink; ctx.textAlign = 'left';
                ctx.fillText(`R2 = ${botR.toFixed(1)}${S.mode === 'circuit' ? ' kΩ' : ' Ω'}`, cx + 30, boxBot + 16);
                ctx.fillStyle = cyan; ctx.fillText(`V2 = Vout = ${Vout.toFixed(1)} V`, cx + 30, boxBot + 34);
                // output tap between the two resistors
                const tapY = (boxTop + 40 + boxBot) / 2;
                ctx.strokeStyle = cyan; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(cx, tapY); ctx.lineTo(cx + w * 0.42, tapY); ctx.stroke();
                ctx.fillStyle = cyan; ctx.beginPath(); ctx.arc(cx + w * 0.42, tapY, 4, 0, Math.PI * 2); ctx.fill();
                ctx.font = '700 11px Inter'; ctx.textAlign = 'left'; ctx.fillText('output', cx + w * 0.42 + 8, tapY + 4);
                // Vout bar (right)
                const vbx = w * 0.9, vby0 = top, vbh = bot - top;
                ctx.fillStyle = 'rgba(255,255,255,.06)'; ctx.fillRect(vbx - 8, vby0, 16, vbh);
                ctx.fillStyle = cyan; const fillH = vbh * Vout / VS; ctx.fillRect(vbx - 8, vby0 + vbh - fillH, 16, fillH);
                // equation readout
                ctx.fillStyle = ink; ctx.font = '700 12px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                if (S.mode === 'divider') {
                    ctx.fillText(`R1/R2 = ${(R1 / R2).toFixed(2)}   V1/V2 = ${(V1 / Vout).toFixed(2)}  ✓`, w * 0.5, h - 26);
                    ctx.fillStyle = faint; ctx.font = '600 10px Inter';
                    ctx.fillText('the resistors SHARE the supply p.d. in the ratio of their resistances', w * 0.5, h - 10);
                } else {
                    ctx.fillStyle = faint; ctx.font = '600 10px Inter';
                    ctx.fillText(`${sensor === 'thermistor' ? 'hotter' : 'brighter'} → sensor R falls → output p.d. across R2 RISES (an input sensor)`, w * 0.5, h - 10);
                }
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const badge = { sensor: 'Input sensor · R changes', divider: 'Potential divider · R1/R2 = V1/V2', circuit: 'Sensor in a divider' }[mode];

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
                    <button className={'cw-btn ' + (mode === 'sensor' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('sensor')}>🌡 Sensor</button>
                    <button className={'cw-btn ' + (mode === 'divider' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('divider')}>⎓ Divider</button>
                    <button className={'cw-btn ' + (mode === 'circuit' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('circuit')}>⚡ Sensor circuit</button>
                </div>

                {(mode === 'sensor' || mode === 'circuit') && (
                    <div className="cw-btnrow">
                        <button className={'cw-btn ' + (sensor === 'thermistor' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setSensor('thermistor')}>NTC thermistor</button>
                        <button className={'cw-btn ' + (sensor === 'ldr' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setSensor('ldr')}>LDR</button>
                    </div>
                )}

                {mode === 'sensor' && (
                    <>
                        <Stat label={sensor === 'thermistor' ? 'thermistor resistance' : 'LDR resistance'} value={`${Rsen.toFixed(1)} kΩ`} tone="acc"
                              sub={<>an <b>NTC thermistor's</b> resistance <b>falls</b> as it gets <b>hotter</b>; an <b>LDR's</b> resistance <b>falls</b> as it gets <b>brighter</b>. That changing resistance is how they <b>sense</b> their surroundings.</>} />
                        <Slider label={sensor === 'thermistor' ? 'temperature' : 'light level'} value={stimulus} min={0} max={100} step={1} onChange={setStimulus} format={(x) => `${x} %`} />
                        <Flag kind="neutral">
                            A <b>thermistor</b> (NTC = negative temperature coefficient) and a <b>light-dependent resistor (LDR)</b> are <b>input sensors</b>: their <b>resistance changes</b> with a physical quantity. For an <b>NTC thermistor</b>, resistance <b>decreases</b> as temperature <b>rises</b>; for an <b>LDR</b>, resistance <b>decreases</b> as the light gets <b>brighter</b>. Feeding that resistance into a circuit lets it <b>respond</b> to heat or light.
                        </Flag>
                    </>
                )}

                {mode === 'divider' && (
                    <>
                        <Stat label="output p.d. (across R2)" value={`${Vout_div.toFixed(1)} V`} tone="acc"
                              sub={<>a <b>potential divider</b> is two resistors in series across a supply; the output is taken across one of them. They <b>share</b> the supply p.d. so that <b>R1/R2 = V1/V2</b>. Here R1/R2 = {(R1 / R2).toFixed(2)} = V1/V2.</>} />
                        <Slider label="wiper (output share)" value={ratio} min={5} max={95} step={1} onChange={setRatio} format={(x) => `${x}%`} />
                        <Flag kind="neutral">
                            In a <b>potential divider</b>, two resistors in series <b>share</b> the supply p.d. in the <b>ratio of their resistances</b>:
                            <b> R1 / R2 = V1 / V2</b>. Move the wiper (change the split) and the output p.d. <b>V<sub>out</sub></b> across R2 changes — a bigger R2 takes a bigger share. A <b>variable</b> potential divider lets you select any output p.d. from 0 up to the supply.
                        </Flag>
                    </>
                )}

                {mode === 'circuit' && (
                    <>
                        <Stat label="output p.d." value={`${Vout_ckt.toFixed(1)} V`} tone="acc"
                              sub={<>the <b>sensor is R1</b>, a fixed resistor is R2, output across R2. As the {sensor === 'thermistor' ? 'temperature' : 'light'} rises the sensor's R <b>falls</b> to {Rsen.toFixed(1)} kΩ, so the output p.d. <b>rises</b> — the circuit <b>responds</b> to its surroundings.</>} />
                        <Slider label={sensor === 'thermistor' ? 'temperature' : 'light level'} value={stimulus} min={0} max={100} step={1} onChange={setStimulus} format={(x) => `${x} %`} />
                        <Flag kind="neutral">
                            Put the sensor into a <b>potential divider</b> and you have an <b>input-sensor circuit</b>. As the {sensor === 'thermistor' ? 'temperature' : 'light'} rises, the sensor's resistance <b>falls</b>, so it takes a <b>smaller share</b> of the supply p.d. and the <b>output across the fixed resistor rises</b>. That rising output p.d. can switch on a heater, a light or an alarm — a <b>temperature</b> or <b>light</b> sensor.
                        </Flag>
                    </>
                )}

                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, stimulus, ratio, sensor })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
