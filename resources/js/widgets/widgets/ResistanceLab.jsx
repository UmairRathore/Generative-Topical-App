import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: resistance_lab ───────────────────────────────────────────────────
// Bespoke Resistance & Ohm's law hero (5054 · 4.2.4). Three modes:
//   MEASURE — a resistor with a variable supply, an ammeter (series) and a
//             voltmeter (parallel); change V, read I, and R = V / I is computed
//             live (constant for an ohmic resistor at constant temperature).
//   GRAPH   — the current–voltage characteristic, switchable between an ohmic
//             resistor (straight line through the origin), a filament lamp (an
//             S-curve that flattens as it heats) and a diode (current one way).
//   WIRE    — a wire whose resistance rises with length (R ∝ L) and falls with
//             cross-sectional area (R ∝ 1/A).
//
// Immersive 2D by the dimensionality doctrine (circuits/graphs are schematic).
// config: { mode, component, voltage, length, area }

const COMPONENTS = ['resistor', 'lamp', 'diode'];

export default function ResistanceLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['measure', 'graph', 'wire'].includes(config.mode) ? config.mode : 'measure');
    const [component, setComponent] = useState(COMPONENTS.includes(config.component) ? config.component : 'resistor');
    const [voltage, setVoltage] = useState(typeof config.voltage === 'number' ? config.voltage : 6);
    const [length, setLength] = useState(typeof config.length === 'number' ? config.length : 1);
    const [area, setArea] = useState(typeof config.area === 'number' ? config.area : 1);
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, component, voltage, length, area };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, component: st.current.component, voltage: st.current.voltage, length: st.current.length, area: st.current.area }),
            setState: (s) => {
                if (['measure', 'graph', 'wire'].includes(s?.mode)) setMode(s.mode);
                if (COMPONENTS.includes(s?.component)) setComponent(s.component);
                if (typeof s?.voltage === 'number') setVoltage(Math.max(0, Math.min(10, s.voltage)));
                if (typeof s?.length === 'number') setLength(Math.max(0.5, Math.min(4, s.length)));
                if (typeof s?.area === 'number') setArea(Math.max(0.5, Math.min(4, s.area)));
            },
        });
    }, [onReady]); // eslint-disable-line

    const R_MEAS = 5; // ohms (ohmic resistor for measure mode)
    const I_meas = voltage / R_MEAS;
    const R_wire = (2 * length / area);

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, t0 = performance.now();
        // I–V characteristic (I in arbitrary units, V in volts, symmetric where relevant)
        const charOf = (comp, v) => {
            if (comp === 'resistor') return v * 0.55;
            if (comp === 'lamp') return Math.sign(v) * 2.6 * Math.sqrt(Math.abs(v)); // flattens (R rises with heat)
            // diode: ~0 for reverse & below threshold, then rises steeply forward
            return v > 0.6 ? 1.7 * Math.pow(v - 0.6, 1.6) : (v < -0.1 ? -0.02 * (-v) : 0);
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
            bg.addColorStop(0, '#12161d'); bg.addColorStop(1, '#0b0e14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);

            if (S.mode === 'measure') {
                const L = w * 0.2, R = w * 0.8, T = h * 0.34, B = h * 0.72;
                ctx.strokeStyle = 'rgba(170,185,205,.7)'; ctx.lineWidth = 3; ctx.beginPath(); ctx.rect(L, T, R - L, B - T); ctx.stroke();
                // supply on left
                ctx.strokeStyle = 'rgba(230,235,245,.85)'; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(L - 10, (T + B) / 2 - 8); ctx.lineTo(L + 10, (T + B) / 2 - 8); ctx.stroke(); ctx.lineWidth = 5; ctx.beginPath(); ctx.moveTo(L - 6, (T + B) / 2 + 8); ctx.lineTo(L + 6, (T + B) / 2 + 8); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.textAlign = 'center'; ctx.fillText('variable supply', L, (T + B) / 2 + h * 0.2);
                // ammeter in series (top)
                const ax = (L + R) / 2, ay = T;
                ctx.fillStyle = '#0e1620'; ctx.strokeStyle = amber; ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(ax, ay, 16, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
                ctx.fillStyle = amber; ctx.font = '700 12px Inter'; ctx.textBaseline = 'middle'; ctx.fillText('A', ax, ay); ctx.textBaseline = 'alphabetic';
                // resistor on right
                const rx = R, ry = (T + B) / 2;
                ctx.fillStyle = 'rgba(251,191,36,.18)'; ctx.strokeStyle = amber; ctx.lineWidth = 2; ctx.beginPath(); ctx.roundRect(rx - 8, ry - 22, 16, 44, 4); ctx.fill(); ctx.stroke();
                // voltmeter parallel across resistor
                const vx = R + w * 0.1, vy = ry;
                ctx.strokeStyle = 'rgba(120,200,255,.5)'; ctx.lineWidth = 1.6; ctx.beginPath(); ctx.moveTo(rx + 8, ry - 15); ctx.lineTo(vx, ry - 15); ctx.lineTo(vx, vy - 14); ctx.moveTo(rx + 8, ry + 15); ctx.lineTo(vx, ry + 15); ctx.lineTo(vx, vy + 14); ctx.stroke();
                ctx.fillStyle = '#0e1620'; ctx.strokeStyle = cyan; ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(vx, vy, 15, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
                ctx.fillStyle = cyan; ctx.font = '700 12px Inter'; ctx.textBaseline = 'middle'; ctx.fillText('V', vx, vy); ctx.textBaseline = 'alphabetic';
                // readouts
                const I = S.voltage / R_MEAS;
                ctx.fillStyle = ink; ctx.font = '700 12px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(`V = ${S.voltage.toFixed(1)} V    I = ${I.toFixed(2)} A`, w * 0.5, h - 30);
                ctx.fillStyle = acc; ctx.fillText(`R = V / I = ${S.voltage.toFixed(1)} / ${I.toFixed(2)} = ${R_MEAS.toFixed(1)} Ω  (constant)`, w * 0.5, h - 12);
            } else if (S.mode === 'graph') {
                const gx0 = w * 0.14, gx1 = w * 0.9, gy0 = h * 0.12, gy1 = h * 0.82, cx = (gx0 + gx1) / 2, cyv = (gy0 + gy1) / 2;
                const diode = S.component === 'diode';
                const ox = diode ? gx0 + (gx1 - gx0) * 0.28 : cx;   // origin x (diode shifts left)
                const oy = diode ? gy1 - 20 : cyv;                  // origin y (diode near bottom)
                // axes
                ctx.strokeStyle = 'rgba(255,255,255,.25)'; ctx.lineWidth = 1;
                ctx.beginPath(); ctx.moveTo(gx0, oy); ctx.lineTo(gx1, oy); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(ox, gy0); ctx.lineTo(ox, gy1); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.textAlign = 'left';
                ctx.fillText('current I', ox + 4, gy0 + 8); ctx.fillText('voltage V', gx1 - 46, oy - 4);
                // characteristic
                const sx = (gx1 - gx0) / 12, sy = (gy1 - gy0) / 10;
                ctx.strokeStyle = acc; ctx.lineWidth = 2.4; ctx.beginPath();
                const vmin = diode ? -2 : -5, vmax = 5;
                for (let v = vmin; v <= vmax; v += 0.05) {
                    const i = charOf(S.component, v);
                    const px = ox + v * sx, py = oy - i * sy * 0.9;
                    v === vmin ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
                }
                ctx.stroke();
                // sweeping point
                const sv = vmin + ((amb * 0.6) % 1) * (vmax - vmin);
                const si = charOf(S.component, sv);
                ctx.fillStyle = '#fff2c0'; ctx.beginPath(); ctx.arc(ox + sv * sx, oy - si * sy * 0.9, 4, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                const cap = { resistor: 'ohmic resistor → straight line through the origin (R constant): I ∝ V', lamp: 'filament lamp → curve flattens as it heats (R rises with temperature)', diode: 'diode → current flows one way only (almost none in reverse)' }[S.component];
                ctx.fillText(cap, w * 0.5, h - 10);
            } else {
                // WIRE — R ∝ length, R ∝ 1/area
                const cy = h * 0.42;
                const len = S.length, ar = S.area;
                const x0 = w * 0.14, maxLen = w * 0.62;
                const wireLen = maxLen * (len / 4);
                const wireH = 6 + 14 * (ar / 4);
                ctx.fillStyle = 'rgba(200,150,90,.6)'; ctx.strokeStyle = 'rgba(230,180,120,.8)'; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.roundRect(x0, cy - wireH / 2, wireLen, wireH, 3); ctx.fill(); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.textAlign = 'left';
                ctx.fillText(`length = ${len.toFixed(1)}  (R ∝ length)`, x0, cy - wireH / 2 - 10);
                ctx.fillText(`cross-sectional area = ${ar.toFixed(1)}  (R ∝ 1/area)`, x0, cy + wireH / 2 + 18);
                ctx.fillStyle = acc; ctx.font = '700 15px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(`resistance ∝ length / area  =  ${R_wire.toFixed(2)}  (rel.)`, w * 0.5, h * 0.78);
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif';
                ctx.fillText('a longer wire has MORE resistance; a thicker (bigger-area) wire has LESS', w * 0.5, h - 12);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const badge = { measure: 'Measuring resistance · R = V / I', graph: 'Current–voltage graphs', wire: 'Resistance of a wire · length & area' }[mode];

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
                    <button className={'cw-btn ' + (mode === 'measure' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('measure')}>🔧 Measure R</button>
                    <button className={'cw-btn ' + (mode === 'graph' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('graph')}>📈 I–V graph</button>
                    <button className={'cw-btn ' + (mode === 'wire' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('wire')}>➖ Wire</button>
                </div>

                {mode === 'measure' && (
                    <>
                        <Stat label="resistance  R = V / I" value={`${R_MEAS.toFixed(1)} Ω`} tone="acc"
                              sub={<>connect a <b>voltmeter</b> (parallel) and <b>ammeter</b> (series), read V and I, then <b>R = V / I</b>. For an ohmic resistor at constant temperature, R stays the <b>same</b> — that is <b>Ohm's law</b>.</>} />
                        <Slider label="supply voltage V" value={voltage} min={0} max={10} step={0.5} onChange={setVoltage} format={(x) => `${x.toFixed(1)} V`} />
                        <Flag kind="neutral">
                            <b>Resistance R = V / I</b> (p.d. ÷ current), in <b>ohms (Ω)</b>. Measure it by reading the <b>p.d.</b> across the
                            component (voltmeter in parallel) and the <b>current</b> through it (ammeter in series), then dividing.
                            <b> Ohm's law</b>: for a metal conductor at <b>constant temperature</b>, the current is proportional to the
                            p.d., so R is <b>constant</b> — try it, R = V/I stays 5 Ω whatever V you choose.
                        </Flag>
                    </>
                )}

                {mode === 'graph' && (
                    <>
                        <Stat label={`I–V graph: ${component}`} value={component === 'resistor' ? 'straight line (Ω constant)' : component === 'lamp' ? 'curve (Ω rises with heat)' : 'one-way (diode)'} tone="acc"
                              sub={component === 'resistor' ? <>an <b>ohmic resistor</b>: I ∝ V, a <b>straight line through the origin</b> — the resistance (V/I) is the same everywhere</> : component === 'lamp' ? <>a <b>filament lamp</b>: as V rises the filament <b>heats up</b>, so its <b>resistance increases</b> and the graph <b>curves</b> (flattens)</> : <>a <b>diode</b>: it only lets current flow <b>one way</b> — almost none in reverse</>} />
                        <div className="cw-btnrow">
                            {COMPONENTS.map((c) => <button key={c} className={'cw-btn ' + (component === c ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setComponent(c)}>{c === 'resistor' ? 'Resistor' : c === 'lamp' ? 'Filament lamp' : 'Diode'}</button>)}
                        </div>
                        <Flag kind="neutral">
                            The <b>I–V characteristic</b> reveals the component. A <b>resistor</b> of constant resistance gives a
                            <b> straight line through the origin</b> (I ∝ V). A <b>filament lamp</b> gives a <b>curve</b>: as the current
                            heats the filament, its <b>resistance increases</b>, so the graph bends over. A <b>diode</b> conducts in
                            <b> only one direction</b> — a large current forwards, almost none in reverse.
                        </Flag>
                    </>
                )}

                {mode === 'wire' && (
                    <>
                        <Stat label="resistance of a wire" value={`${R_wire.toFixed(2)} (rel.)`} tone="acc"
                              sub={<>for a wire, <b>R ∝ length</b> (longer = more resistance) and <b>R ∝ 1 / area</b> (thicker = less resistance).</>} />
                        <Slider label="length" value={length} min={0.5} max={4} step={0.5} onChange={setLength} format={(x) => `${x.toFixed(1)}×`} />
                        <Slider label="cross-sectional area (thickness)" value={area} min={0.5} max={4} step={0.5} onChange={setArea} format={(x) => `${x.toFixed(1)}×`} />
                        <Flag kind="neutral">
                            For a given wire material, the resistance depends on its shape: it is <b>directly proportional to the
                            length</b> (double the length → double the resistance) and <b>inversely proportional to the
                            cross-sectional area</b> (double the thickness/area → half the resistance). So a <b>long thin</b> wire has a
                            high resistance; a <b>short thick</b> one has a low resistance.
                        </Flag>
                    </>
                )}

                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, component, voltage, length, area })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
