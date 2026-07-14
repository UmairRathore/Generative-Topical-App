import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag } from '../primitives.jsx';

// ── Widget: volt_lab ─────────────────────────────────────────────────────────
// Bespoke e.m.f. / p.d. hero (5054 · 4.2.3). Three modes:
//   ENERGY — a coulomb of charge travels round a circuit carrying an energy bar:
//            it GAINS energy at the cell (the source does work → e.m.f. = W/Q)
//            and GIVES it up at the component (work done on it → p.d. = W/Q).
//            1 volt = 1 joule per coulomb.
//   METER  — a voltmeter connected in PARALLEL across a component reads the p.d.
//            in volts (analogue needle + digital), contrasting with the in-series
//            ammeter.
//   CELLS  — cells in SERIES (e.m.f.s add: 1.5 + 1.5 = 3.0 V) versus in PARALLEL
//            (e.m.f. stays 1.5 V, but the supply lasts longer).
//
// Immersive 2D by the dimensionality doctrine (circuits are schematic).
// config: { mode, arrangement }

export default function VoltLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['energy', 'meter', 'cells'].includes(config.mode) ? config.mode : 'energy');
    const [arrangement, setArrangement] = useState(config.arrangement === 'parallel' ? 'parallel' : 'series');
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, arrangement };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, arrangement: st.current.arrangement }),
            setState: (s) => {
                if (['energy', 'meter', 'cells'].includes(s?.mode)) setMode(s.mode);
                if (s?.arrangement === 'series' || s?.arrangement === 'parallel') setArrangement(s.arrangement);
            },
        });
    }, [onReady]); // eslint-disable-line

    const totalEmf = arrangement === 'series' ? 3.0 : 1.5;

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

            const cellSym = (x, y, vert) => {
                ctx.strokeStyle = 'rgba(230,235,245,.85)';
                if (vert) {
                    ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(x - 10, y - 8); ctx.lineTo(x + 10, y - 8); ctx.stroke();
                    ctx.lineWidth = 5; ctx.beginPath(); ctx.moveTo(x - 6, y + 8); ctx.lineTo(x + 6, y + 8); ctx.stroke();
                    ctx.fillStyle = rose; ctx.font = '700 11px Inter'; ctx.textAlign = 'right'; ctx.fillText('+', x - 12, y - 4); ctx.fillStyle = cyan; ctx.fillText('−', x - 12, y + 12);
                } else {
                    ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(x - 8, y - 10); ctx.lineTo(x - 8, y + 10); ctx.stroke();
                    ctx.lineWidth = 5; ctx.beginPath(); ctx.moveTo(x + 8, y - 7); ctx.lineTo(x + 8, y + 7); ctx.stroke();
                }
            };

            if (S.mode === 'energy') {
                const L = w * 0.2, R = w * 0.8, T = h * 0.28, B = h * 0.66;
                ctx.strokeStyle = 'rgba(170,185,205,.7)'; ctx.lineWidth = 3; ctx.beginPath(); ctx.rect(L, T, R - L, B - T); ctx.stroke();
                cellSym(L, (T + B) / 2, true);
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('cell (source)', L, (T + B) / 2 + h * 0.16);
                // resistor on the right edge
                const compY = (T + B) / 2;
                ctx.fillStyle = 'rgba(251,191,36,.18)'; ctx.strokeStyle = amber; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.roundRect(R - 8, compY - 22, 16, 44, 4); ctx.fill(); ctx.stroke();
                ctx.fillStyle = faint; ctx.fillText('component', R, compY + h * 0.20);
                // a charge travelling round with an energy bar; energy rises at cell, falls at component
                const per = 2 * (R - L) + 2 * (B - T);
                const pos = (t) => { let d = t * per; const ww = R - L, hh = B - T;
                    if (d < hh) return { x: L, y: B - d, seg: 'left' }; d -= hh;      // up left edge (bottom→top, past cell)
                    if (d < ww) return { x: L + d, y: T, seg: 'top' }; d -= ww;        // top L→R
                    if (d < hh) return { x: R, y: T + d, seg: 'right' }; d -= hh;      // down right (past component)
                    return { x: R - d, y: B, seg: 'bottom' }; };                       // bottom R→L
                const tc = (amb * 0.14) % 1; const p = pos(tc);
                // energy of the charge: high on top (after cell), low on bottom (after component)
                let energy;
                if (p.seg === 'left') energy = (B - p.y) / (B - T);        // rising through the cell
                else if (p.seg === 'top') energy = 1;                     // full, carrying to component
                else if (p.seg === 'right') energy = 1 - (p.y - T) / (B - T); // dropping through the component
                else energy = 0;                                         // spent, returning to cell
                // charge dot + its energy bar
                ctx.fillStyle = '#fff2c0'; ctx.beginPath(); ctx.arc(p.x, p.y, 7, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = '#0b0e14'; ctx.font = '700 8px Inter'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                ctx.fillText('1C', p.x, p.y); ctx.textBaseline = 'alphabetic';
                const bw = 40, bh = 6; const bx = Math.min(w - bw - 6, Math.max(6, p.x - bw / 2)), by = p.y - 16;
                ctx.fillStyle = 'rgba(255,255,255,.15)'; ctx.fillRect(bx, by, bw, bh);
                ctx.fillStyle = energy > 0.5 ? acc : amber; ctx.fillRect(bx, by, bw * energy, bh);
                // labels
                ctx.fillStyle = acc; ctx.font = '700 11px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText('at the cell: charge GAINS energy', L - 4, T - 24);
                ctx.fillStyle = acc; ctx.fillText('e.m.f.  E = W / Q  (energy given per coulomb)', L - 4, T - 10);
                ctx.fillStyle = amber; ctx.textAlign = 'right';
                ctx.fillText('at the component: charge GIVES UP energy', R + 4, B + 22);
                ctx.fillText('p.d.  V = W / Q  (energy delivered per coulomb)', R + 4, B + 36);
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('both are measured in volts:  1 volt = 1 joule per coulomb (J/C)', w * 0.5, h - 12);
            } else if (S.mode === 'meter') {
                const L = w * 0.22, R = w * 0.78, T = h * 0.34, B = h * 0.7;
                ctx.strokeStyle = 'rgba(170,185,205,.7)'; ctx.lineWidth = 3; ctx.beginPath(); ctx.rect(L, T, R - L, B - T); ctx.stroke();
                cellSym(L, (T + B) / 2, true);
                // component (resistor) on the top edge (middle)
                const rx = (L + R) / 2, ry = T;
                ctx.fillStyle = 'rgba(251,191,36,.18)'; ctx.strokeStyle = amber; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.roundRect(rx - 26, ry - 8, 52, 16, 4); ctx.fill(); ctx.stroke();
                // voltmeter connected in PARALLEL across the resistor (above it)
                const vy = T - h * 0.16;
                ctx.strokeStyle = 'rgba(120,200,255,.6)'; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.moveTo(rx - 26, ry); ctx.lineTo(rx - 26, vy); ctx.lineTo(rx - 20, vy); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(rx + 26, ry); ctx.lineTo(rx + 26, vy); ctx.lineTo(rx + 20, vy); ctx.stroke();
                ctx.fillStyle = '#0e1620'; ctx.strokeStyle = cyan; ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(rx, vy, 20, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
                ctx.fillStyle = cyan; ctx.font = '700 15px Inter'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText('V', rx, vy); ctx.textBaseline = 'alphabetic';
                const na = -Math.PI * 0.75 + 0.6 * Math.PI * 1.5;
                ctx.strokeStyle = rose; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(rx, vy); ctx.lineTo(rx + Math.cos(na) * 14, vy + Math.sin(na) * 14); ctx.stroke();
                ctx.fillStyle = '#0a140d'; ctx.fillRect(rx - 30, vy + 26, 60, 20); ctx.fillStyle = acc; ctx.font = '700 14px "JetBrains Mono", monospace'; ctx.textAlign = 'center'; ctx.fillText('1.5 V', rx, vy + 41);
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('a voltmeter is connected in PARALLEL (across the component) to read the p.d. in volts', w * 0.5, h - 12);
            } else {
                // CELLS — series vs parallel
                const cy = h * 0.42; const par = S.arrangement === 'parallel';
                if (!par) {
                    // two cells in series
                    cellSym(w * 0.36, cy, false); cellSym(w * 0.52, cy, false);
                    ctx.strokeStyle = 'rgba(170,185,205,.7)'; ctx.lineWidth = 2.5;
                    ctx.beginPath(); ctx.moveTo(w * 0.36 + 8, cy); ctx.lineTo(w * 0.52 - 8, cy); ctx.stroke();
                    ctx.fillStyle = acc; ctx.font = '700 13px Inter, sans-serif'; ctx.textAlign = 'center';
                    ctx.fillText('in SERIES: e.m.f.s ADD', w * 0.5, cy - 40);
                    ctx.fillStyle = ink; ctx.font = '700 16px "JetBrains Mono", monospace';
                    ctx.fillText('1.5 V + 1.5 V = 3.0 V', w * 0.5, cy + 44);
                } else {
                    // two cells in parallel (side by side)
                    cellSym(w * 0.44, cy - 16, false); cellSym(w * 0.44, cy + 16, false);
                    ctx.strokeStyle = 'rgba(170,185,205,.7)'; ctx.lineWidth = 2.5;
                    ctx.beginPath(); ctx.moveTo(w * 0.44 - 8, cy - 16); ctx.lineTo(w * 0.44 - 8, cy + 16); ctx.moveTo(w * 0.44 + 8, cy - 16); ctx.lineTo(w * 0.44 + 8, cy + 16); ctx.stroke();
                    ctx.fillStyle = amber; ctx.font = '700 13px Inter, sans-serif'; ctx.textAlign = 'center';
                    ctx.fillText('in PARALLEL: e.m.f. UNCHANGED', w * 0.5, cy - 44);
                    ctx.fillStyle = ink; ctx.font = '700 16px "JetBrains Mono", monospace';
                    ctx.fillText('1.5 V (same as one) — but lasts longer', w * 0.5, cy + 48);
                }
                ctx.fillStyle = faint; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(par ? 'parallel identical cells: total e.m.f. = e.m.f. of ONE cell' : 'series cells: total e.m.f. = the sum of the individual e.m.f.s', w * 0.5, h - 12);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const badge = { energy: 'e.m.f. & p.d. · energy per coulomb', meter: 'The voltmeter · measuring p.d.', cells: 'Cells in series & parallel' }[mode];

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
                    <button className={'cw-btn ' + (mode === 'energy' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('energy')}>⚡ Energy</button>
                    <button className={'cw-btn ' + (mode === 'meter' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('meter')}>🎛 Voltmeter</button>
                    <button className={'cw-btn ' + (mode === 'cells' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('cells')}>🔋 Cells</button>
                </div>

                {mode === 'energy' && (
                    <>
                        <Stat label="e.m.f. and p.d. — energy per coulomb" value="1 V = 1 J/C" tone="acc"
                              sub={<><b>e.m.f.</b> is the energy the <b>source</b> gives each coulomb (E = W/Q); <b>p.d.</b> is the energy each coulomb <b>gives up</b> in a component (V = W/Q). Both in <b>volts</b>.</>} />
                        <Flag kind="neutral">
                            Follow one coulomb of charge round the circuit. At the <b>cell</b> it <b>gains</b> energy — the source does
                            work on it — and the energy per coulomb the source gives is the <b>e.m.f.</b> (E = W/Q). In the
                            <b> component</b> the charge <b>gives up</b> that energy — it does work — and the energy per coulomb
                            delivered is the <b>p.d.</b> (V = W/Q). Both are measured in <b>volts</b>, where <b>1 V = 1 joule per
                            coulomb (J/C)</b>.
                        </Flag>
                    </>
                )}

                {mode === 'meter' && (
                    <>
                        <Stat label="measuring p.d. — the voltmeter" value="connected in parallel" tone="acc"
                              sub={<>a <b>voltmeter</b> measures the <b>p.d.</b> across a component, in <b>volts</b>. It is connected in <b>parallel</b> (across the component) — the opposite of an ammeter, which goes in series.</>} />
                        <Flag kind="neutral">
                            A <b>voltmeter</b> measures the <b>potential difference (p.d.)</b> across a component, in <b>volts</b>. It is
                            always connected in <b>parallel</b> — <b>across</b> the component — so it reads the energy given up per
                            coulomb there. Like ammeters, voltmeters can be <b>analogue</b> (needle) or <b>digital</b> (display) and
                            come in different <b>ranges</b>. (Remember: <b>ammeter → series, voltmeter → parallel</b>.)
                        </Flag>
                    </>
                )}

                {mode === 'cells' && (
                    <>
                        <Stat label={arrangement === 'series' ? 'cells in series' : 'identical cells in parallel'} value={`${totalEmf.toFixed(1)} V total`} tone={arrangement === 'series' ? 'acc' : 'warn'}
                              sub={arrangement === 'series' ? <>in <b>series</b>, the e.m.f.s <b>add up</b>: two 1.5 V cells give <b>3.0 V</b></> : <>identical cells in <b>parallel</b> give the e.m.f. of <b>one</b> cell (1.5 V) — but can supply current for <b>longer</b></>} />
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (arrangement === 'series' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setArrangement('series')}>Series</button>
                            <button className={'cw-btn ' + (arrangement === 'parallel' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setArrangement('parallel')}>Parallel</button>
                        </div>
                        <Flag kind="neutral">
                            Connect cells in <b>series</b> (+ to −) and their e.m.f.s <b>add</b>: two 1.5 V cells give a total of
                            <b> 3.0 V</b>. Connect <b>identical</b> cells in <b>parallel</b> and the total e.m.f. is just the e.m.f. of
                            <b> one</b> cell (1.5 V) — you don't get more voltage, but the cells share the current so the supply
                            <b> lasts longer</b>.
                        </Flag>
                    </>
                )}

                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, arrangement })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
