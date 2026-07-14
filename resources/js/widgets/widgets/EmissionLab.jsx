import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag } from '../primitives.jsx';

// ── Widget: emission_lab ─────────────────────────────────────────────────────
// Bespoke 5.2.2 hero (immersive 2D). The three types of emission.
//   TYPES       — what alpha, beta and gamma ARE (α = 2p+2n = a helium nucleus,
//                 charge +2; β = a high-speed electron from the nucleus, −1;
//                 γ = a high-frequency EM wave, no charge), emitted spontaneously
//                 and in random directions.
//   PENETRATION — relative penetrating power and ionising effect: α stopped by
//                 paper (but most ionising), β stopped by a few mm of aluminium,
//                 γ only reduced by thick lead (but least ionising).
//   FIELDS      — deflection in an electric or magnetic field: α (+) and β (−)
//                 bend opposite ways (β more, being far lighter), γ undeflected.
// config: { mode, field }

export default function EmissionLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['types', 'penetration', 'fields'].includes(config.mode) ? config.mode : 'types');
    const [field, setField] = useState(config.field === 'magnetic' ? 'magnetic' : 'electric');
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, field };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, field: st.current.field }),
            setState: (s) => {
                if (['types', 'penetration', 'fields'].includes(s?.mode)) setMode(s.mode);
                if (['electric', 'magnetic'].includes(s?.field)) setField(s.field);
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
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', cyan = '#38BDF8', rose = '#FB7185', purple = '#a78bfa';
            const S = st.current;
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#12161d'); bg.addColorStop(1, '#0b0e14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);
            ctx.textAlign = 'center'; ctx.lineCap = 'round';
            const A_COL = rose, B_COL = cyan, G_COL = purple;

            if (S.mode === 'types') {
                // a nucleus emitting the three types in random directions
                const cx = w * 0.28, cy = h * 0.45;
                ctx.fillStyle = amber; ctx.beginPath(); ctx.arc(cx, cy, 16, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = '#0b0e14'; ctx.font = '700 9px Inter'; ctx.fillText('nucleus', cx, cy + 3);
                // random-direction emission rays
                const rays = [[-0.5, A_COL], [0.3, B_COL], [1.1, G_COL], [2.0, A_COL], [-1.4, B_COL], [3.0, G_COL]];
                rays.forEach(([a0, col], i) => { const a = a0 + Math.sin(amb * 0.4 + i) * 0.05; ctx.strokeStyle = col; ctx.lineWidth = 1.6; ctx.globalAlpha = 0.5; ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(cx + Math.cos(a) * 70, cy + Math.sin(a) * 70); ctx.stroke(); ctx.globalAlpha = 1; });
                // the three type cards on the right
                const card = (y, col, sym, name, detail) => {
                    ctx.fillStyle = col; ctx.font = '800 20px "JetBrains Mono", monospace'; ctx.textAlign = 'left'; ctx.fillText(sym, w * 0.5, y);
                    ctx.fillStyle = ink; ctx.font = '700 11px Inter'; ctx.fillText(name, w * 0.56, y - 6);
                    ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.fillText(detail, w * 0.56, y + 8);
                };
                card(h * 0.28, A_COL, 'α', 'alpha  (charge +2)', '2 protons + 2 neutrons = a helium nucleus');
                card(h * 0.5, B_COL, 'β', 'beta  (charge −1)', 'a high-speed electron from the nucleus');
                card(h * 0.72, G_COL, 'γ', 'gamma  (no charge)', 'a high-frequency electromagnetic wave');
                ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.textAlign = 'center';
                ctx.fillText('emission from a nucleus is SPONTANEOUS and RANDOM in direction', w * 0.5, h - 12);
            } else if (S.mode === 'penetration') {
                // three horizontal beams meeting barriers: paper, aluminium, lead
                const rows = [
                    { y: h * 0.26, col: A_COL, name: 'α (alpha)', stop: 0, ion: 'most ionising' },
                    { y: h * 0.5, col: B_COL, name: 'β (beta)', stop: 1, ion: 'medium' },
                    { y: h * 0.74, col: G_COL, name: 'γ (gamma)', stop: 2, ion: 'least ionising' },
                ];
                const bx = [w * 0.42, w * 0.6, w * 0.78];
                const bl = ['paper', 'few mm aluminium', 'thick lead'];
                // barriers
                bx.forEach((x, i) => { ctx.fillStyle = i === 0 ? '#d8d2b8' : i === 1 ? '#9aa4b2' : '#4a4f57'; ctx.fillRect(x - 5, h * 0.16, 10 + i * 4, h * 0.7); ctx.fillStyle = faint; ctx.font = '600 8px Inter'; ctx.save(); ctx.translate(x + 4, h * 0.9); ctx.fillText(bl[i], 0, 0); ctx.restore(); });
                rows.forEach((r) => {
                    ctx.fillStyle = r.col; ctx.font = '700 10px Inter'; ctx.textAlign = 'right'; ctx.fillText(r.name, w * 0.2, r.y + 3);
                    const endX = r.stop === 0 ? bx[0] : r.stop === 1 ? bx[1] : bx[2] + 8;
                    // moving particles up to the stopping barrier
                    ctx.strokeStyle = r.col; ctx.lineWidth = r.stop === 0 ? 3 : r.stop === 1 ? 2 : 1.4; ctx.beginPath(); ctx.moveTo(w * 0.22, r.y); ctx.lineTo(endX, r.y); ctx.stroke();
                    for (let i = 0; i < 4; i++) { const u = ((i / 4 + amb * 0.5) % 1); const x = w * 0.22 + u * (endX - w * 0.22); ctx.fillStyle = r.col; ctx.beginPath(); ctx.arc(x, r.y, 2.6, 0, Math.PI * 2); ctx.fill(); }
                    ctx.fillStyle = faint; ctx.font = '600 8px Inter'; ctx.textAlign = 'left'; ctx.fillText(r.ion, endX + 8, r.y + 3);
                });
                ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.textAlign = 'center';
                ctx.fillText('penetration: α stopped by paper · β by a few mm of aluminium · γ only reduced by thick lead', w * 0.5, h - 10);
            } else {
                // FIELDS: three beams enter a field region and deflect
                const isE = S.field === 'electric';
                const rx0 = w * 0.3, rx1 = w * 0.8, ry0 = h * 0.16, ry1 = h * 0.82, cyMid = (ry0 + ry1) / 2;
                if (isE) {
                    ctx.fillStyle = rose; ctx.font = '800 14px Inter'; ctx.fillText('+', (rx0 + rx1) / 2, ry0 - 4);
                    ctx.fillStyle = cyan; ctx.fillText('−', (rx0 + rx1) / 2, ry1 + 16);
                    ctx.strokeStyle = 'rgba(200,210,225,.4)'; ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(rx0, ry0); ctx.lineTo(rx1, ry0); ctx.moveTo(rx0, ry1); ctx.lineTo(rx1, ry1); ctx.stroke();
                } else {
                    ctx.fillStyle = faint; ctx.font = '700 11px Inter'; for (let gx = rx0; gx < rx1; gx += 26) for (let gy = ry0 + 14; gy < ry1; gy += 26) ctx.fillText('×', gx, gy);
                    ctx.fillText('magnetic field into the page', (rx0 + rx1) / 2, ry0 - 6);
                }
                // beams from the left, deflecting inside the field
                const beam = (col, sign, mass, label) => {
                    ctx.strokeStyle = col; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(w * 0.06, cyMid);
                    let x = w * 0.06, y = cyMid, vx = 3, vy = 0;
                    for (; x < w * 0.94; ) { if (x > rx0 && x < rx1 && sign !== 0) vy += sign * (0.5 / mass); x += vx; y += vy; ctx.lineTo(x, y); if (y < ry0 - 30 || y > ry1 + 30) break; }
                    ctx.stroke();
                    ctx.fillStyle = col; ctx.font = '700 10px Inter'; ctx.textAlign = 'left'; ctx.fillText(label, x + 4, y);
                };
                // in E: + deflects toward − plate (down = +y here since − is at bottom); β(−) up; α(+) down but less (heavy)
                // in B (into page), F = qv×B: +x velocity, B into page(−z): v×B = (+x)×(−z) = +y (down) for +q; up for −q
                beam(A_COL, +1, 8, 'α (+2, heavy → small deflection)');
                beam(B_COL, -1, 1, 'β (−1, light → large deflection)');
                beam(G_COL, 0, 1, 'γ (no charge → undeflected)');
                ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.textAlign = 'center';
                ctx.fillText(isE ? 'in an ELECTRIC field: α and β bend OPPOSITE ways (opposite charge); γ goes straight' : 'in a MAGNETIC field: α and β bend OPPOSITE ways; β bends more (lighter); γ goes straight', w * 0.5, h - 10);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const badge = { types: 'What α, β and γ are', penetration: 'Penetration & ionising power', fields: 'Deflection in fields' }[mode];

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
                    <button className={'cw-btn ' + (mode === 'types' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('types')}>☢ Types</button>
                    <button className={'cw-btn ' + (mode === 'penetration' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('penetration')}>🧱 Penetration</button>
                    <button className={'cw-btn ' + (mode === 'fields' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('fields')}>🧲 Fields</button>
                </div>

                {mode === 'types' && (
                    <>
                        <Stat label="the three emissions" value="α, β and γ" tone="acc"
                              sub={<><b>α</b> = 2 protons + 2 neutrons (a <b>helium nucleus</b>), charge <b>+2</b>. <b>β</b> = a <b>high-speed electron</b> from the nucleus, charge <b>−1</b>. <b>γ</b> = a <b>high-frequency EM wave</b>, <b>no charge</b>.</>} />
                        <Flag kind="neutral">
                            Radiation is emitted from an unstable nucleus <b>spontaneously</b> (you cannot predict or trigger it) and in
                            <b> random directions</b>. There are three types: an <b>alpha (α) particle</b> is <b>two protons and two neutrons</b> —
                            a <b>helium nucleus</b> (charge +2); a <b>beta (β) particle</b> is a <b>high-speed electron</b> emitted from the nucleus
                            (charge −1); and <b>gamma (γ) radiation</b> is a <b>high-frequency electromagnetic wave</b> (no charge, no mass).
                        </Flag>
                    </>
                )}

                {mode === 'penetration' && (
                    <>
                        <Stat label="penetrating power" value="γ > β > α" tone="acc"
                              sub={<><b>α</b> is stopped by <b>paper</b>; <b>β</b> by a <b>few mm of aluminium</b>; <b>γ</b> is only reduced by <b>thick lead</b>. Ionising power is the <b>reverse</b>: α is the <b>most</b> ionising, γ the <b>least</b>.</>} />
                        <Flag kind="neutral">
                            The three radiations differ in how far they penetrate and how strongly they ionise. <b>Alpha</b> is the <b>most ionising</b>
                            but the <b>least penetrating</b> — stopped by a sheet of <b>paper</b> (or a few cm of air). <b>Beta</b> is in between —
                            stopped by a <b>few millimetres of aluminium</b>. <b>Gamma</b> is the <b>least ionising</b> but <b>most penetrating</b> —
                            only <b>reduced</b> by <b>thick lead</b> (or concrete). So the order of penetration is <b>γ > β > α</b>, and of ionising is
                            <b> α > β > γ</b>.
                        </Flag>
                    </>
                )}

                {mode === 'fields' && (
                    <>
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (field === 'electric' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setField('electric')}>electric field</button>
                            <button className={'cw-btn ' + (field === 'magnetic' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setField('magnetic')}>magnetic field</button>
                        </div>
                        <Stat label={field === 'electric' ? 'in an electric field' : 'in a magnetic field'} value="α ↔ β opposite; γ straight" tone="acc"
                              sub={<><b>α (+)</b> and <b>β (−)</b> are deflected in <b>opposite</b> directions because they have <b>opposite charge</b>; <b>β deflects more</b> (it is far lighter). <b>γ</b> has <b>no charge</b>, so it is <b>not deflected</b>.</>} />
                        <Flag kind="neutral">
                            Because <b>α</b> and <b>β</b> are <b>charged</b>, they are <b>deflected</b> by electric and magnetic fields — and in
                            <b> opposite directions</b>, because their charges are opposite (α is +2, β is −1). The <b>β</b> particle is deflected
                            <b> much more</b> than the α, because it is <b>far lighter</b> (and faster). <b>γ</b> radiation has <b>no charge</b>, so a
                            field has <b>no effect</b> on it — it passes straight through undeflected. (This is a neat way to tell the three apart.)
                        </Flag>
                    </>
                )}

                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, field })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
