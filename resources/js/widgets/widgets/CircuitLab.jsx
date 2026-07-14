import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: circuit_lab ──────────────────────────────────────────────────────
// Bespoke Circuits hero (5054 · 4.3.1 symbols + 4.3.2 series/parallel). Modes:
//   SYMBOLS  — a gallery of standard circuit symbols with names (cell, battery,
//              lamp, resistor, variable resistor, switch, ammeter, voltmeter,
//              fuse, diode, LED, thermistor, LDR, motor).
//   SERIES   — two resistors in series: the SAME current everywhere, the p.d.s
//              ADD, and the combined resistance is R1 + R2.
//   PARALLEL — two resistors in parallel: the SAME p.d. across each, the currents
//              ADD, and the combined resistance is less than either (product/sum).
//
// Immersive 2D by the dimensionality doctrine (circuits are schematic).
// config: { mode, r1, r2 }

const SYMBOLS = [
    ['cell', 'cell'], ['battery', 'battery'], ['lamp', 'lamp'], ['resistor', 'resistor'],
    ['rheostat', 'variable resistor'], ['switch', 'switch'], ['ammeter', 'ammeter'], ['voltmeter', 'voltmeter'],
    ['fuse', 'fuse'], ['diode', 'diode'], ['led', 'LED'], ['thermistor', 'thermistor'],
    ['ldr', 'LDR'], ['motor', 'motor'], ['supply', 'power supply'], ['heater', 'heater'],
];

export default function CircuitLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['symbols', 'series', 'parallel'].includes(config.mode) ? config.mode : 'symbols');
    const [r1, setR1] = useState(typeof config.r1 === 'number' ? config.r1 : 4);
    const [r2, setR2] = useState(typeof config.r2 === 'number' ? config.r2 : 2);
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, r1, r2 };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, r1: st.current.r1, r2: st.current.r2 }),
            setState: (s) => {
                if (['symbols', 'series', 'parallel'].includes(s?.mode)) setMode(s.mode);
                if (typeof s?.r1 === 'number') setR1(Math.max(1, Math.min(10, s.r1)));
                if (typeof s?.r2 === 'number') setR2(Math.max(1, Math.min(10, s.r2)));
            },
        });
    }, [onReady]); // eslint-disable-line

    const EMF = 6;
    const Rser = r1 + r2, Rpar = (r1 * r2) / (r1 + r2);
    const Iser = EMF / Rser, Ipar = EMF / Rpar;

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
            const wire = 'rgba(200,210,225,.85)';
            ctx.lineCap = 'round'; ctx.lineJoin = 'round';

            // draw a symbol centred at (x,y), spanning a horizontal 'sp' with leads
            const sym = (x, y, sp, type) => {
                ctx.strokeStyle = wire; ctx.lineWidth = 1.8; ctx.fillStyle = 'none';
                const a = x - sp / 2, b = x + sp / 2;
                const lead = (from, to) => { ctx.beginPath(); ctx.moveTo(from, y); ctx.lineTo(to, y); ctx.stroke(); };
                const circ = (r, label, col) => { ctx.strokeStyle = col || wire; ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.stroke(); if (label) { ctx.fillStyle = col || wire; ctx.font = '700 11px Inter'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(label, x, y); ctx.textBaseline = 'alphabetic'; } };
                if (type === 'cell') { lead(a, x - 4); lead(x + 6, b); ctx.lineWidth = 1.8; ctx.beginPath(); ctx.moveTo(x - 4, y - 9); ctx.lineTo(x - 4, y + 9); ctx.stroke(); ctx.lineWidth = 4; ctx.beginPath(); ctx.moveTo(x + 4, y - 5); ctx.lineTo(x + 4, y + 5); ctx.stroke(); }
                else if (type === 'battery') { lead(a, x - 12); lead(x + 14, b); for (let i = 0; i < 2; i++) { const cx0 = x - 8 + i * 12; ctx.lineWidth = 1.8; ctx.beginPath(); ctx.moveTo(cx0 - 4, y - 9); ctx.lineTo(cx0 - 4, y + 9); ctx.stroke(); ctx.lineWidth = 4; ctx.beginPath(); ctx.moveTo(cx0, y - 5); ctx.lineTo(cx0, y + 5); ctx.stroke(); } }
                else if (type === 'lamp') { lead(a, x - 11); lead(x + 11, b); circ(11); ctx.beginPath(); ctx.moveTo(x - 8, y - 8); ctx.lineTo(x + 8, y + 8); ctx.moveTo(x + 8, y - 8); ctx.lineTo(x - 8, y + 8); ctx.stroke(); }
                else if (type === 'resistor' || type === 'heater') { lead(a, x - 15); lead(x + 15, b); ctx.strokeRect(x - 15, y - 7, 30, 14); }
                else if (type === 'rheostat') { lead(a, x - 15); lead(x + 15, b); ctx.strokeRect(x - 15, y - 7, 30, 14); ctx.beginPath(); ctx.moveTo(x - 12, y + 12); ctx.lineTo(x + 12, y - 12); ctx.stroke(); const ang = Math.atan2(-24, 24); ctx.beginPath(); ctx.moveTo(x + 12, y - 12); ctx.lineTo(x + 12 - Math.cos(ang - .5) * 6, y - 12 - Math.sin(ang - .5) * 6); ctx.lineTo(x + 12 - Math.cos(ang + .5) * 6, y - 12 - Math.sin(ang + .5) * 6); ctx.closePath(); ctx.fillStyle = wire; ctx.fill(); }
                else if (type === 'switch') { lead(a, x - 10); lead(x + 10, b); ctx.fillStyle = wire; ctx.beginPath(); ctx.arc(x - 10, y, 2.2, 0, Math.PI * 2); ctx.arc(x + 10, y, 2.2, 0, Math.PI * 2); ctx.fill(); ctx.beginPath(); ctx.moveTo(x - 10, y); ctx.lineTo(x + 8, y - 9); ctx.stroke(); }
                else if (type === 'ammeter') { lead(a, x - 11); lead(x + 11, b); circ(11, 'A', amber); }
                else if (type === 'voltmeter') { lead(a, x - 11); lead(x + 11, b); circ(11, 'V', cyan); }
                else if (type === 'motor') { lead(a, x - 11); lead(x + 11, b); circ(11, 'M', acc); }
                else if (type === 'fuse') { lead(a, x - 15); lead(x + 15, b); ctx.strokeRect(x - 15, y - 6, 30, 12); ctx.beginPath(); ctx.moveTo(x - 15, y); ctx.lineTo(x + 15, y); ctx.stroke(); }
                else if (type === 'diode' || type === 'led') { lead(a, x - 9); lead(x + 9, b); ctx.fillStyle = wire; ctx.beginPath(); ctx.moveTo(x - 9, y - 9); ctx.lineTo(x - 9, y + 9); ctx.lineTo(x + 9, y); ctx.closePath(); ctx.fill(); ctx.beginPath(); ctx.moveTo(x + 9, y - 9); ctx.lineTo(x + 9, y + 9); ctx.stroke(); if (type === 'led') { ctx.strokeStyle = amber; for (let i = 0; i < 2; i++) { ctx.beginPath(); ctx.moveTo(x + 2 + i * 6, y - 12); ctx.lineTo(x + 8 + i * 6, y - 18); ctx.stroke(); ctx.beginPath(); ctx.moveTo(x + 8 + i * 6, y - 18); ctx.lineTo(x + 5 + i * 6, y - 16); ctx.moveTo(x + 8 + i * 6, y - 18); ctx.lineTo(x + 7 + i * 6, y - 14.5); ctx.stroke(); } } }
                else if (type === 'thermistor') { lead(a, x - 15); lead(x + 15, b); ctx.strokeRect(x - 15, y - 7, 30, 14); ctx.beginPath(); ctx.moveTo(x - 18, y + 13); ctx.lineTo(x - 6, y + 13); ctx.lineTo(x + 18, y - 13); ctx.stroke(); }
                else if (type === 'ldr') { lead(a, x - 14); lead(x + 14, b); circ(14); ctx.strokeRect(x - 9, y - 5, 18, 10); ctx.strokeStyle = amber; for (let i = 0; i < 2; i++) { ctx.beginPath(); ctx.moveTo(x - 4 + i * 8, y - 20); ctx.lineTo(x - 8 + i * 8, y - 12); ctx.stroke(); ctx.beginPath(); ctx.moveTo(x - 8 + i * 8, y - 12); ctx.lineTo(x - 8 + i * 8, y - 15); ctx.moveTo(x - 8 + i * 8, y - 12); ctx.lineTo(x - 5 + i * 8, y - 13); ctx.stroke(); } }
                else if (type === 'supply') { lead(a, x - 16); lead(x + 16, b); ctx.strokeRect(x - 16, y - 11, 32, 22); ctx.fillStyle = wire; ctx.font = '600 8px Inter'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText('+  −', x, y); ctx.textBaseline = 'alphabetic'; }
            };

            if (S.mode === 'symbols') {
                const cols = 4, rows = 4, mx = w * 0.06, my = h * 0.06, cw = (w - 2 * mx) / cols, ch = (h - 2 * my) / rows;
                SYMBOLS.forEach(([type, name], i) => {
                    const cx = mx + (i % cols) * cw + cw / 2, cy = my + Math.floor(i / cols) * ch + ch * 0.42;
                    sym(cx, cy, Math.min(cw * 0.7, 60), type);
                    ctx.fillStyle = ink; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
                    ctx.fillText(name, cx, cy + ch * 0.34);
                });
            } else if (S.mode === 'series') {
                const L = w * 0.16, R = w * 0.84, T = h * 0.26, B = h * 0.66;
                ctx.strokeStyle = wire; ctx.lineWidth = 2.4;
                ctx.beginPath(); ctx.rect(L, T, R - L, B - T); ctx.stroke();
                sym(L, (T + B) / 2, 26, 'cell');
                sym((L + R) * 0.4, T, 34, 'resistor'); sym((L + R) * 0.62, T, 34, 'resistor');
                sym(R, (T + B) / 2, 26, 'ammeter');
                // moving charge dots (same current all round)
                const per = 2 * (R - L) + 2 * (B - T);
                for (let i = 0; i < 20; i++) { let d = ((i / 20 + amb * 0.1 * (Iser / 1.5)) % 1) * per; let x, yy; const ww = R - L, hh = B - T; if (d < ww) { x = L + d; yy = T; } else if (d < ww + hh) { x = R; yy = T + (d - ww); } else if (d < 2 * ww + hh) { x = R - (d - ww - hh); yy = B; } else { x = L; yy = B - (d - 2 * ww - hh); } ctx.fillStyle = 'rgba(120,200,255,.8)'; ctx.beginPath(); ctx.arc(x, yy, 2.6, 0, Math.PI * 2); ctx.fill(); }
                ctx.fillStyle = amber; ctx.font = '700 10px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(`R1 = ${r1} Ω`, (L + R) * 0.4, T - 14); ctx.fillText(`R2 = ${r2} Ω`, (L + R) * 0.62, T - 14);
                ctx.fillStyle = ink; ctx.font = '700 12px "JetBrains Mono", monospace';
                ctx.fillText(`combined R = R1 + R2 = ${r1} + ${r2} = ${Rser} Ω`, w * 0.5, B + 28);
                ctx.fillStyle = faint; ctx.font = '600 10px Inter, sans-serif';
                ctx.fillText('SERIES: same current everywhere; the p.d.s across R1 and R2 add up', w * 0.5, h - 12);
            } else {
                const L = w * 0.16, R = w * 0.84, T = h * 0.2, B = h * 0.64, midX = (L + R) / 2;
                ctx.strokeStyle = wire; ctx.lineWidth = 2.4;
                // outer loop with two parallel branches between two nodes
                const nA = L + (R - L) * 0.32, nB = L + (R - L) * 0.68, topY = T, botY = B, midY = (T + B) / 2;
                ctx.beginPath(); ctx.moveTo(L, midY); ctx.lineTo(L, T); ctx.lineTo(nA, T); ctx.moveTo(nA, T); ctx.lineTo(nA, B); ctx.lineTo(L, B); ctx.lineTo(L, midY); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(nA, midY); ctx.lineTo(nB, midY); ctx.stroke(); // node link (drawn via branches below)
                // two branches between nA and nB
                ctx.beginPath(); ctx.moveTo(nA, topY + 12); ctx.lineTo(nA, T); ctx.moveTo(nA, T); ctx.lineTo(nA, T); ctx.stroke();
                // simpler: two horizontal branches
                ctx.beginPath();
                ctx.moveTo(nA, midY - 30); ctx.lineTo(nB, midY - 30); // top branch
                ctx.moveTo(nA, midY + 30); ctx.lineTo(nB, midY + 30); // bottom branch
                ctx.moveTo(nA, midY - 30); ctx.lineTo(nA, midY + 30); // left node bus
                ctx.moveTo(nB, midY - 30); ctx.lineTo(nB, midY + 30); // right node bus
                ctx.moveTo(nB, midY); ctx.lineTo(R, midY); ctx.lineTo(R, B); ctx.lineTo(nA, B); ctx.moveTo(nA, midY); ctx.lineTo(nA, B);
                ctx.stroke();
                sym(L, midY, 24, 'cell');
                sym((nA + nB) / 2, midY - 30, 34, 'resistor'); sym((nA + nB) / 2, midY + 30, 34, 'resistor');
                sym(R, midY, 24, 'ammeter');
                ctx.fillStyle = amber; ctx.font = '700 10px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(`R1 = ${r1} Ω`, (nA + nB) / 2, midY - 44); ctx.fillText(`R2 = ${r2} Ω`, (nA + nB) / 2, midY + 52);
                ctx.fillStyle = ink; ctx.font = '700 12px "JetBrains Mono", monospace';
                ctx.fillText(`combined R = (R1×R2)/(R1+R2) = ${(r1 * r2)}/${r1 + r2} = ${Rpar.toFixed(2)} Ω`, w * 0.5, B + 26);
                ctx.fillStyle = faint; ctx.font = '600 10px Inter, sans-serif';
                ctx.fillText('PARALLEL: same p.d. across each; the branch currents add; combined R is LESS than either', w * 0.5, h - 12);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const badge = { symbols: 'Circuit symbols', series: 'Series circuit · R = R1 + R2', parallel: 'Parallel circuit · combined R' }[mode];

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
                    <button className={'cw-btn ' + (mode === 'symbols' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('symbols')}>⚙ Symbols</button>
                    <button className={'cw-btn ' + (mode === 'series' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('series')}>— Series</button>
                    <button className={'cw-btn ' + (mode === 'parallel' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('parallel')}>⑃ Parallel</button>
                </div>

                {mode === 'symbols' && (
                    <>
                        <Stat label="reading a circuit diagram" value="know the standard symbols" tone="acc"
                              sub={<>every component has an agreed <b>symbol</b>. Learn to <b>draw</b> and <b>read</b> them — cells, lamps, resistors, meters, switches, diodes, thermistors, LDRs and more.</>} />
                        <Flag kind="neutral">
                            A <b>circuit diagram</b> uses standard <b>symbols</b> joined by lines (the wires). You need to recognise
                            and draw each one and know how it behaves: a <b>cell/battery</b> drives the current; a <b>lamp/resistor</b>
                            resists it; an <b>ammeter</b> (series) and <b>voltmeter</b> (parallel) measure it; a <b>switch</b> breaks
                            the circuit; a <b>diode</b> allows current one way; a <b>thermistor</b> and <b>LDR</b> are input sensors.
                        </Flag>
                    </>
                )}

                {mode === 'series' && (
                    <>
                        <Stat label="series: combined resistance" value={`${Rser} Ω`} tone="acc"
                              sub={<>in <b>series</b> the resistances simply <b>add</b>: R = R1 + R2 = {r1} + {r2} = <b>{Rser} Ω</b>. The <b>same current</b> flows through every part; the <b>p.d.s add</b> to the supply.</>} />
                        <Slider label="R1" value={r1} min={1} max={10} step={1} onChange={setR1} format={(x) => `${x} Ω`} />
                        <Slider label="R2" value={r2} min={1} max={10} step={1} onChange={setR2} format={(x) => `${x} Ω`} />
                        <Flag kind="neutral">
                            In a <b>series</b> circuit there is <b>one path</b>, so the <b>current is the same</b> everywhere. The
                            <b> potential differences add up</b> to equal the supply, and the <b>combined resistance is the sum</b>:
                            <b> R = R1 + R2 + …</b> Adding resistors in series always <b>increases</b> the total resistance.
                        </Flag>
                    </>
                )}

                {mode === 'parallel' && (
                    <>
                        <Stat label="parallel: combined resistance" value={`${Rpar.toFixed(2)} Ω`} tone="acc"
                              sub={<>for <b>two</b> resistors in parallel, R = (R1×R2)/(R1+R2) = <b>{Rpar.toFixed(2)} Ω</b> — <b>less</b> than either. The <b>p.d. is the same</b> across each branch; the <b>branch currents add</b>.</>} />
                        <Slider label="R1" value={r1} min={1} max={10} step={1} onChange={setR1} format={(x) => `${x} Ω`} />
                        <Slider label="R2" value={r2} min={1} max={10} step={1} onChange={setR2} format={(x) => `${x} Ω`} />
                        <Flag kind="neutral">
                            In a <b>parallel</b> circuit there are <b>several paths</b>, so the <b>same p.d.</b> is across each branch and
                            the <b>branch currents add</b> to the total. For <b>two</b> resistors the combined resistance is
                            <b> R = (R1 × R2) / (R1 + R2)</b> — always <b>less than the smaller</b> one, because extra paths make it
                            easier for current to flow.
                        </Flag>
                    </>
                )}

                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, r1, r2 })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
