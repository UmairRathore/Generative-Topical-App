import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: transformer_lab ──────────────────────────────────────────────────
// Bespoke 4.5.6 hero (immersive 2D). The transformer.
//   TRANSFORMER — an iron core linking a primary coil (Np turns, a.c. in) and a
//                 secondary coil (Ns turns, out). Animated alternating flux runs
//                 round the core; Vp/Vs = Np/Ns is shown live, with step-up /
//                 step-down labelled as you change the turns.
//   GRID        — high-voltage transmission: for a fixed power, transmitting at a
//                 HIGH voltage means a SMALL current (I = P/V) and so a SMALL power
//                 loss (I²R) in the cables — compared with low-voltage transmission.
// config: { mode, np, ns, vp }

export default function TransformerLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['transformer', 'grid'].includes(config.mode) ? config.mode : 'transformer');
    const [np, setNp] = useState(typeof config.np === 'number' ? config.np : 1000);
    const [ns, setNs] = useState(typeof config.ns === 'number' ? config.ns : 200);
    const [vp, setVp] = useState(typeof config.vp === 'number' ? config.vp : 230);
    const [hv, setHv] = useState(config.hv !== false); // grid: transmit at high voltage
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, np, ns, vp, hv };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, np: st.current.np, ns: st.current.ns, vp: st.current.vp, hv: st.current.hv }),
            setState: (s) => {
                if (['transformer', 'grid'].includes(s?.mode)) setMode(s.mode);
                if (typeof s?.np === 'number') setNp(Math.max(100, Math.min(2000, s.np)));
                if (typeof s?.ns === 'number') setNs(Math.max(100, Math.min(2000, s.ns)));
                if (typeof s?.vp === 'number') setVp(Math.max(12, Math.min(400, s.vp)));
                if (typeof s?.hv === 'boolean') setHv(s.hv);
            },
        });
    }, [onReady]); // eslint-disable-line

    const vs = vp * ns / np;
    const stepUp = ns > np;
    // grid: fixed power 100 kW; high-V line 100 kV vs low-V line 1 kV; cable R = 5 Ω
    const P = 100000, R = 5;
    const Vline = hv ? 100000 : 1000;
    const Iline = P / Vline;
    const loss = Iline * Iline * R;
    const lossPct = loss / P * 100;

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
            ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.textAlign = 'center';

            if (S.mode === 'transformer') {
                // iron core: a rectangular frame
                const L = w * 0.30, R2 = w * 0.70, T = h * 0.22, B = h * 0.74, bar = 20;
                ctx.strokeStyle = '#6b7480'; ctx.lineWidth = bar; ctx.lineJoin = 'miter';
                ctx.strokeRect(L, T, R2 - L, B - T);
                ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.fillText('soft-iron core', (L + R2) / 2, (T + B) / 2);
                // flux arrows circulating (a.c.: direction oscillates)
                const fluxDir = Math.sin(amb * 2) >= 0 ? 1 : -1;
                const per = 2 * (R2 - L) + 2 * (B - T), ww = R2 - L, hh = B - T;
                for (let i = 0; i < 8; i++) {
                    let d = ((i / 8 + amb * 0.15 * fluxDir) % 1 + 1) % 1 * per; let x, y, ang;
                    if (d < ww) { x = L + d; y = T; ang = 0; } else if (d < ww + hh) { x = R2; y = T + (d - ww); ang = Math.PI / 2; } else if (d < 2 * ww + hh) { x = R2 - (d - ww - hh); y = B; ang = Math.PI; } else { x = L; y = B - (d - 2 * ww - hh); ang = -Math.PI / 2; }
                    ctx.save(); ctx.translate(x, y); ctx.rotate(ang * fluxDir + (fluxDir < 0 ? Math.PI : 0)); ctx.fillStyle = cyan;
                    ctx.beginPath(); ctx.moveTo(6, 0); ctx.lineTo(-4, -4); ctx.lineTo(-4, 4); ctx.closePath(); ctx.fill(); ctx.restore();
                }
                // primary coil on left leg (Np turns) + secondary on right (Ns turns)
                const drawCoil = (cx, turns, col, label) => {
                    const n = Math.max(3, Math.min(12, Math.round(turns / 150) + 2));
                    const gap = (B - T - 30) / n;
                    ctx.strokeStyle = col; ctx.lineWidth = 2.6;
                    for (let k = 0; k < n; k++) { const yy = T + 15 + k * gap + gap / 2; ctx.beginPath(); ctx.ellipse(cx, yy, 16, gap * 0.42, 0, 0, Math.PI * 2); ctx.stroke(); }
                    ctx.fillStyle = col; ctx.font = '700 10px Inter'; ctx.fillText(label, cx, T - 8);
                };
                drawCoil(L, S.np, amber, 'PRIMARY');
                drawCoil(R2, S.ns, acc, 'SECONDARY');
                // a.c. source on primary (left) + output on secondary (right)
                ctx.strokeStyle = wire; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.moveTo(L - 16, T + 20); ctx.lineTo(w * 0.10, T + 20); ctx.lineTo(w * 0.10, B - 20); ctx.lineTo(L - 16, B - 20); ctx.stroke();
                ctx.beginPath(); ctx.arc(w * 0.10, (T + B) / 2, 12, 0, Math.PI * 2); ctx.stroke();
                ctx.strokeStyle = amber; ctx.beginPath(); for (let x = -6; x <= 6; x++) ctx.lineTo(w * 0.10 + x, (T + B) / 2 - Math.sin(x) * 3); ctx.stroke();
                ctx.fillStyle = amber; ctx.font = '700 9px Inter'; ctx.fillText('a.c. in', w * 0.10, (T + B) / 2 + 24);
                ctx.strokeStyle = wire; ctx.beginPath(); ctx.moveTo(R2 + 16, T + 20); ctx.lineTo(w * 0.90, T + 20); ctx.lineTo(w * 0.90, B - 20); ctx.lineTo(R2 + 16, B - 20); ctx.stroke();
                ctx.strokeStyle = acc; ctx.beginPath(); ctx.arc(w * 0.90, (T + B) / 2, 11, 0, Math.PI * 2); ctx.stroke(); ctx.beginPath(); ctx.moveTo(w * 0.90 - 7, (T + B) / 2 - 7); ctx.lineTo(w * 0.90 + 7, (T + B) / 2 + 7); ctx.moveTo(w * 0.90 + 7, (T + B) / 2 - 7); ctx.lineTo(w * 0.90 - 7, (T + B) / 2 + 7); ctx.stroke();
                ctx.fillStyle = acc; ctx.fillText('a.c. out', w * 0.90, (T + B) / 2 + 24);
                // readouts
                ctx.fillStyle = ink; ctx.font = '700 12px "JetBrains Mono", monospace';
                ctx.fillText(`Vp/Vs = Np/Ns → ${vp}/${vs.toFixed(0)} = ${np}/${ns}`, w * 0.5, B + 26);
                ctx.fillStyle = stepUp ? acc : amber; ctx.font = '800 13px Inter';
                ctx.fillText(stepUp ? 'STEP-UP (more turns on secondary → higher voltage)' : (ns < np ? 'STEP-DOWN (fewer turns on secondary → lower voltage)' : 'equal turns → same voltage'), w * 0.5, h - 12);
            } else {
                // GRID: station → step-up → line → step-down → houses
                const y = h * 0.4;
                const drawBox = (x, wd, label, col) => { ctx.strokeStyle = col || wire; ctx.lineWidth = 2; ctx.strokeRect(x, y - 22, wd, 44); ctx.fillStyle = col || ink; ctx.font = '700 9px Inter'; ctx.fillText(label, x + wd / 2, y + 2); };
                drawBox(w * 0.04, w * 0.14, 'power station', amber);
                drawBox(w * 0.24, w * 0.11, hv ? 'STEP-UP' : 'no step-up', hv ? acc : faint);
                drawBox(w * 0.75, w * 0.11, hv ? 'STEP-DOWN' : 'no step-down', hv ? acc : faint);
                drawBox(w * 0.90, w * 0.08, 'homes', cyan);
                // transmission line (top) between the transformers, with pylons
                const lx = w * 0.35, rx = w * 0.75, ly = h * 0.2;
                ctx.strokeStyle = wire; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.moveTo(w * 0.35, y - 22); ctx.lineTo(w * 0.35, ly); ctx.lineTo(rx, ly); ctx.lineTo(rx, y - 22); ctx.stroke();
                for (let px = lx; px <= rx; px += (rx - lx) / 3) { ctx.beginPath(); ctx.moveTo(px, ly); ctx.lineTo(px, ly + 22); ctx.moveTo(px - 8, ly + 8); ctx.lineTo(px + 8, ly + 8); ctx.stroke(); }
                // current dots on the line: many/dense for high current (low V), sparse for low current
                const dense = hv ? 4 : 14;
                for (let i = 0; i < dense; i++) { let u = ((i / dense + amb * 0.25) % 1); const x = lx + u * (rx - lx); ctx.fillStyle = hv ? cyan : rose; ctx.beginPath(); ctx.arc(x, ly, hv ? 2.5 : 4, 0, Math.PI * 2); ctx.fill(); }
                // heat glow on the cable if low V (high loss)
                if (!hv) { const g = ctx.createLinearGradient(lx, ly - 8, rx, ly - 8); g.addColorStop(0, 'rgba(251,113,133,0)'); g.addColorStop(0.5, 'rgba(251,113,133,.5)'); g.addColorStop(1, 'rgba(251,113,133,0)'); ctx.strokeStyle = g; ctx.lineWidth = 8; ctx.beginPath(); ctx.moveTo(lx, ly); ctx.lineTo(rx, ly); ctx.stroke(); }
                ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.fillText('transmission line (cable resistance R = 5 Ω)', (lx + rx) / 2, ly - 12);
                // readouts
                ctx.fillStyle = ink; ctx.font = '700 12px "JetBrains Mono", monospace';
                ctx.fillText(`transmit ${(P / 1000)} kW at ${Vline >= 1000 ? (Vline / 1000) + ' kV' : Vline + ' V'} → I = P/V = ${Iline.toFixed(Iline < 10 ? 1 : 0)} A`, w * 0.5, h * 0.68);
                ctx.fillStyle = hv ? acc : rose; ctx.font = '800 13px "JetBrains Mono", monospace';
                ctx.fillText(`power lost in cables = I²R = ${loss >= 1000 ? (loss / 1000).toFixed(0) + ' kW' : loss.toFixed(0) + ' W'} (${lossPct.toFixed(lossPct < 1 ? 2 : 0)}% of the power)`, w * 0.5, h * 0.68 + 24);
                ctx.fillStyle = faint; ctx.font = '600 10px Inter';
                ctx.fillText(hv ? 'HIGH voltage → small current → tiny I²R loss: efficient transmission' : 'LOW voltage → huge current → large I²R loss: wasteful (cables overheat)', w * 0.5, h - 12);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{mode === 'transformer' ? 'Transformer · Vp/Vs = Np/Ns' : 'High-voltage transmission'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'transformer' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('transformer')}>⊟ Transformer</button>
                    <button className={'cw-btn ' + (mode === 'grid' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('grid')}>⚡ Transmission</button>
                </div>
                {mode === 'transformer' ? (
                    <>
                        <Stat label={stepUp ? 'step-up transformer' : (ns < np ? 'step-down transformer' : 'transformer')} value={`Vs = ${vs.toFixed(0)} V`} tone="acc"
                              sub={<><b>Vp/Vs = Np/Ns</b>: {vp}/{vs.toFixed(0)} = {np}/{ns}. A changing a.c. current in the <b>primary</b> makes a <b>changing flux</b> in the iron core, which <b>induces</b> an a.c. voltage in the <b>secondary</b>. {stepUp ? 'More turns on the secondary → a HIGHER voltage (step-up).' : ns < np ? 'Fewer turns on the secondary → a LOWER voltage (step-down).' : ''}</>} />
                        <Slider label="primary turns Np" value={np} min={100} max={2000} step={100} onChange={setNp} format={(x) => `${x}`} />
                        <Slider label="secondary turns Ns" value={ns} min={100} max={2000} step={100} onChange={setNs} format={(x) => `${x}`} />
                        <Slider label="primary voltage Vp" value={vp} min={12} max={400} step={2} onChange={setVp} format={(x) => `${x} V`} />
                        <Flag kind="neutral">
                            A <b>transformer</b> has two coils wound on a <b>soft-iron core</b>: the <b>primary</b> (input) and the
                            <b> secondary</b> (output). An <b>alternating</b> current in the primary makes a <b>continually changing magnetic
                            flux</b> in the core; the core carries this flux to the secondary, where the changing flux <b>induces</b> an
                            alternating voltage (electromagnetic induction — so it only works with <b>a.c.</b>). The voltages are in the ratio of
                            the turns: <b>Vp / Vs = Np / Ns</b>. More turns on the secondary → a higher voltage (<b>step-up</b>); fewer →
                            a lower voltage (<b>step-down</b>).
                        </Flag>
                    </>
                ) : (
                    <>
                        <Stat label="power lost in the cables" value={loss >= 1000 ? `${(loss / 1000).toFixed(0)} kW` : `${loss.toFixed(0)} W`} tone={hv ? 'acc' : 'warn'}
                              sub={<>transmitting {P / 1000} kW at <b>{Vline >= 1000 ? (Vline / 1000) + ' kV' : Vline + ' V'}</b> needs a current <b>I = P/V = {Iline.toFixed(Iline < 10 ? 1 : 0)} A</b>. The cables (R = 5 Ω) waste <b>I²R = {loss >= 1000 ? (loss / 1000).toFixed(0) + ' kW' : loss.toFixed(0) + ' W'}</b> ({lossPct.toFixed(lossPct < 1 ? 2 : 0)}%). {hv ? 'Tiny loss.' : 'Huge loss!'}</>} />
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (hv ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setHv(true)}>transmit at HIGH voltage</button>
                            <button className={'cw-btn ' + (!hv ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setHv(false)}>transmit at LOW voltage</button>
                        </div>
                        <Flag kind="neutral">
                            Electricity is sent across the country at <b>very high voltage</b> for one reason: to cut the <b>power wasted in the
                            cables</b>. For a fixed power <b>P = V I</b>, a <b>higher voltage</b> means a <b>smaller current</b> (I = P/V). The power
                            lost heating the cables is <b>I²R</b>, and because it depends on the <b>square</b> of the current, a smaller current
                            means a <b>much</b> smaller loss. So the grid uses <b>step-up</b> transformers to transmit at high voltage (small
                            current, small loss) and <b>step-down</b> transformers to bring it back to a safe voltage for homes.
                        </Flag>
                    </>
                )}
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, np, ns, vp, hv })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
