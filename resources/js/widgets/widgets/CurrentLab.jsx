import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: current_lab ──────────────────────────────────────────────────────
// Bespoke Electric Current hero (5054 · 4.2.2). Three modes:
//   FLOW   — a circuit loop where free electrons drift out of the − terminal and
//            round to the + terminal, while the CONVENTIONAL current arrow points
//            the opposite way (+ to −); a point P counts the charge passing per
//            second, giving I = Q / t.
//   ACDC   — direct current (a steady one-way drift, a flat current-time graph)
//            versus alternating current (electrons oscillate back and forth, a
//            sine current-time graph).
//   METER  — an ammeter (analogue needle + digital display) in a series circuit,
//            reading the current in amps, with a range.
//
// Immersive 2D by the dimensionality doctrine (circuits are schematic).
// config: { mode, current, dc }

export default function CurrentLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['flow', 'acdc', 'meter'].includes(config.mode) ? config.mode : 'flow');
    const [current, setCurrent] = useState(typeof config.current === 'number' ? config.current : 2);
    const [dc, setDc] = useState(config.dc !== false);
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, current, dc };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, current: st.current.current, dc: st.current.dc }),
            setState: (s) => {
                if (['flow', 'acdc', 'meter'].includes(s?.mode)) setMode(s.mode);
                if (typeof s?.current === 'number') setCurrent(Math.max(0.2, Math.min(3, s.current)));
                if (typeof s?.dc === 'boolean') setDc(s.dc);
            },
        });
    }, [onReady]); // eslint-disable-line

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
            const NEG = '#4D9DFF';
            const S = st.current;
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#12161d'); bg.addColorStop(1, '#0b0e14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);

            const arrow = (x0, y0, x1, y1, col, wide = 2.6) => {
                ctx.strokeStyle = col; ctx.lineWidth = wide; ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke();
                const a = Math.atan2(y1 - y0, x1 - x0);
                ctx.fillStyle = col; ctx.beginPath(); ctx.moveTo(x1, y1);
                ctx.lineTo(x1 - Math.cos(a - 0.4) * 9, y1 - Math.sin(a - 0.4) * 9);
                ctx.lineTo(x1 - Math.cos(a + 0.4) * 9, y1 - Math.sin(a + 0.4) * 9); ctx.closePath(); ctx.fill();
            };
            const cell = (x, yt, yb) => {  // vertical cell on the left edge; + at top, − at bottom
                ctx.strokeStyle = 'rgba(230,235,245,.85)'; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.moveTo(x - 10, (yt + yb) / 2 - 12); ctx.lineTo(x + 10, (yt + yb) / 2 - 12); ctx.stroke(); // long plate (+)
                ctx.lineWidth = 5; ctx.beginPath(); ctx.moveTo(x - 6, (yt + yb) / 2 + 12); ctx.lineTo(x + 6, (yt + yb) / 2 + 12); ctx.stroke(); // short plate (−)
                ctx.fillStyle = rose; ctx.font = '700 12px Inter, sans-serif'; ctx.textAlign = 'right';
                ctx.fillText('+', x - 14, (yt + yb) / 2 - 8); ctx.fillStyle = NEG; ctx.fillText('−', x - 14, (yt + yb) / 2 + 18);
            };

            if (S.mode === 'flow' || S.mode === 'meter') {
                const L = w * 0.2, R = w * 0.8, T = h * 0.3, B = h * 0.7;
                // wire loop
                ctx.strokeStyle = 'rgba(170,185,205,.7)'; ctx.lineWidth = 3;
                ctx.beginPath(); ctx.rect(L, T, R - L, B - T); ctx.stroke();
                cell(L, T, B);
                // component on the right edge: lamp (flow) or ammeter (meter)
                const compY = (T + B) / 2;
                if (S.mode === 'meter') {
                    ctx.fillStyle = '#0e1620'; ctx.strokeStyle = amber; ctx.lineWidth = 2;
                    ctx.beginPath(); ctx.arc(R, compY, 20, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
                    ctx.fillStyle = amber; ctx.font = '700 14px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                    ctx.fillText('A', R, compY); ctx.textBaseline = 'alphabetic';
                    // needle
                    const frac = Math.min(1, S.current / 3);
                    const na = -Math.PI * 0.75 + frac * Math.PI * 1.5;
                    ctx.strokeStyle = rose; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(R, compY); ctx.lineTo(R + Math.cos(na) * 14, compY + Math.sin(na) * 14); ctx.stroke();
                    // digital readout
                    ctx.fillStyle = '#0a140d'; ctx.fillRect(R - 34, compY + 28, 68, 22);
                    ctx.fillStyle = acc; ctx.font = '700 15px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                    ctx.fillText(S.current.toFixed(2) + ' A', R, compY + 44);
                    ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.fillText('ammeter (range 0–3 A)', R, compY - 30);
                } else {
                    // lamp
                    ctx.fillStyle = 'rgba(251,191,36,.25)'; ctx.strokeStyle = amber; ctx.lineWidth = 2;
                    ctx.beginPath(); ctx.arc(R, compY, 15, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
                    ctx.strokeStyle = amber; ctx.beginPath(); ctx.moveTo(R - 10, compY - 10); ctx.lineTo(R + 10, compY + 10); ctx.moveTo(R + 10, compY - 10); ctx.lineTo(R - 10, compY + 10); ctx.stroke();
                }
                // free electrons drifting round the loop (out of − at bottom-left, CCW)
                const per = 2 * (R - L) + 2 * (B - T);
                const posOnLoop = (t) => { // t in [0,1); walk bottom(L->R), right(B->T), top(R->L), left(T->B)
                    let d = t * per;
                    const wSeg = R - L, hSeg = B - T;
                    if (d < wSeg) return { x: L + d, y: B }; d -= wSeg;
                    if (d < hSeg) return { x: R, y: B - d }; d -= hSeg;
                    if (d < wSeg) return { x: R - d, y: T }; d -= wSeg;
                    return { x: L, y: T + d };
                };
                const nE = 26; const spd = 0.06 * (S.current / 2);
                for (let i = 0; i < nE; i++) {
                    const p = posOnLoop((i / nE + amb * spd) % 1);
                    ctx.fillStyle = NEG; ctx.beginPath(); ctx.arc(p.x, p.y, 3.4, 0, Math.PI * 2); ctx.fill();
                }
                // conventional current arrow (+ to −): out of + (top-left) → along top to the right
                arrow(L + (R - L) * 0.28, T - 14, L + (R - L) * 0.62, T - 14, acc, 3);
                ctx.fillStyle = acc; ctx.font = '700 10px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('conventional current  I  (+ → −)', (L + R) / 2, T - 22);
                // electron flow arrow (− to +): on the bottom the electrons drift left→right
                // (out of − and away along the bottom wire) — must match the drifting dots.
                arrow(L + (R - L) * 0.28, B + 16, L + (R - L) * 0.62, B + 16, NEG, 2.4);
                ctx.fillStyle = NEG; ctx.textAlign = 'center';
                ctx.fillText('electron flow  (− → +), the opposite way', (L + R) / 2, B + 30);
                if (S.mode === 'flow') {
                    // point P + I = Q/t
                    const px = (L + R) / 2;
                    ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(px, T, 3, 0, Math.PI * 2); ctx.fill();
                    ctx.fillStyle = ink; ctx.font = '600 10px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                    ctx.fillText(`at P:  I = Q / t = ${S.current.toFixed(1)} C / 1 s = ${S.current.toFixed(1)} A`, w * 0.5, h - 12);
                }
            } else {
                // ACDC — electrons in a wire + current-time graph
                const wireY = h * 0.3, x0 = w * 0.1, x1 = w * 0.9;
                ctx.strokeStyle = 'rgba(170,185,205,.6)'; ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(x0, wireY); ctx.lineTo(x1, wireY); ctx.stroke();
                const nE = 22;
                for (let i = 0; i < nE; i++) {
                    let x;
                    if (S.dc) x = x0 + ((i / nE + amb * 0.12) % 1) * (x1 - x0);       // steady one-way drift
                    else x = (x0 + x1) / 2 + Math.sin(amb * 3 + i * 0.5) * (x1 - x0) * 0.34; // oscillate back & forth
                    ctx.fillStyle = NEG; ctx.beginPath(); ctx.arc(x, wireY, 3.4, 0, Math.PI * 2); ctx.fill();
                }
                if (S.dc) { arrow(w * 0.42, wireY - 16, w * 0.58, wireY - 16, acc, 2.4); ctx.fillStyle = acc; }
                else { const d = Math.sin(amb * 3) > 0 ? 1 : -1; arrow(w * 0.5 - d * 20, wireY - 16, w * 0.5 + d * 20, wireY - 16, amber, 2.4); ctx.fillStyle = amber; }
                ctx.font = '700 11px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(S.dc ? 'direct current (d.c.): electrons drift ONE way' : 'alternating current (a.c.): electrons go back and forth', w * 0.5, wireY - 26);
                // current-time graph
                const gx0 = w * 0.1, gx1 = w * 0.9, gy = h * 0.72, gh = h * 0.2;
                ctx.strokeStyle = 'rgba(255,255,255,.2)'; ctx.lineWidth = 1;
                ctx.beginPath(); ctx.moveTo(gx0, gy); ctx.lineTo(gx1, gy); ctx.stroke(); // time axis (I=0)
                ctx.beginPath(); ctx.moveTo(gx0, gy - gh); ctx.lineTo(gx0, gy + gh); ctx.stroke(); // I axis
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText('current I', gx0 + 4, gy - gh + 4); ctx.fillText('time', gx1 - 24, gy + 12);
                ctx.strokeStyle = S.dc ? acc : amber; ctx.lineWidth = 2.2; ctx.beginPath();
                for (let px = 0; px <= gx1 - gx0; px += 2) {
                    const y = S.dc ? gy - gh * 0.55 : gy - Math.sin((px / (gx1 - gx0)) * Math.PI * 4 - amb * 3) * gh * 0.7;
                    px === 0 ? ctx.moveTo(gx0 + px, y) : ctx.lineTo(gx0 + px, y);
                }
                ctx.stroke();
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(S.dc ? 'd.c. → a steady, flat current-time graph' : 'a.c. → the current reverses: a graph that swings + and −', w * 0.5, h - 12);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const badge = { flow: 'Current · free electrons & I = Q / t', acdc: 'Direct vs alternating current', meter: 'Measuring current · the ammeter' }[mode];

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
                    <button className={'cw-btn ' + (mode === 'flow' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('flow')}>🔋 Flow</button>
                    <button className={'cw-btn ' + (mode === 'acdc' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('acdc')}>〜 a.c. / d.c.</button>
                    <button className={'cw-btn ' + (mode === 'meter' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('meter')}>🎛 Ammeter</button>
                </div>

                {mode === 'flow' && (
                    <>
                        <Stat label="electric current  I = Q / t" value={`${current.toFixed(1)} A`} tone="acc"
                              sub={<><b>Current</b> is the <b>charge passing a point per second</b>: I = Q / t. In a metal it is <b>free electrons</b> drifting. Measured in <b>amps</b> (1 A = 1 coulomb per second, C/s).</>} />
                        <Slider label="current" value={current} min={0.2} max={3} step={0.1} onChange={setCurrent} format={(x) => `${x.toFixed(1)} A`} />
                        <Flag kind="neutral">
                            An <b>electric current</b> is the rate of flow of charge: <b>I = Q / t</b> (charge ÷ time), measured in
                            <b> amps (A)</b>, where <b>1 A = 1 C/s</b>. In a metal, the current is a drift of <b>free electrons</b>.
                            Note the two directions are <b>opposite</b>: <b>conventional current</b> is defined from <b>+ to −</b>,
                            but the <b>free electrons</b> actually flow from <b>− to +</b>.
                        </Flag>
                    </>
                )}

                {mode === 'acdc' && (
                    <>
                        <Stat label={dc ? 'direct current (d.c.)' : 'alternating current (a.c.)'} value={dc ? 'one steady direction' : 'reverses back and forth'} tone={dc ? 'acc' : 'warn'}
                              sub={dc ? <>in <b>d.c.</b> the charge flows in <b>one direction only</b> (e.g. from a cell/battery) — a flat current-time graph</> : <>in <b>a.c.</b> the direction <b>keeps reversing</b> (e.g. the mains) — a current-time graph that swings positive and negative</>} />
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (dc ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setDc(true)}>Direct (d.c.)</button>
                            <button className={'cw-btn ' + (!dc ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setDc(false)}>Alternating (a.c.)</button>
                        </div>
                        <Flag kind="neutral">
                            <b>Direct current (d.c.)</b> flows in <b>one direction only</b> — the electrons drift steadily one way (a
                            <b> cell or battery</b> gives d.c.). <b>Alternating current (a.c.)</b> <b>reverses direction</b> repeatedly —
                            the electrons vibrate back and forth (the <b>mains</b> supply is a.c.). On a current–time graph, d.c. is a
                            flat line and a.c. swings between positive and negative.
                        </Flag>
                    </>
                )}

                {mode === 'meter' && (
                    <>
                        <Stat label="measuring current — the ammeter" value={`${current.toFixed(2)} A`} tone="acc"
                              sub={<>an <b>ammeter</b> is connected in <b>series</b> to measure the current in <b>amps</b>. <b>Analogue</b> ammeters use a needle on a scale; <b>digital</b> ones show a number. Choose a <b>range</b> that fits the current.</>} />
                        <Slider label="current through the ammeter" value={current} min={0.2} max={3} step={0.05} onChange={setCurrent} format={(x) => `${x.toFixed(2)} A`} />
                        <Flag kind="neutral">
                            An <b>ammeter</b> measures the current in <b>amps</b> and is always connected <b>in series</b> (in the line
                            the current flows through). An <b>analogue</b> ammeter has a moving <b>needle</b> on a scale; a <b>digital</b>
                            one gives a numerical <b>display</b>. Ammeters come in different <b>ranges</b> (e.g. 0–1 A, 0–5 A) — pick one
                            whose range comfortably covers the current you expect.
                        </Flag>
                    </>
                )}

                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, current, dc })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
