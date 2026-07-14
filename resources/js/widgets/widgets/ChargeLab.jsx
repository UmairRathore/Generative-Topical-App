import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag } from '../primitives.jsx';

// ── Widget: charge_lab ───────────────────────────────────────────────────────
// Bespoke Electric Charge hero (5054 · 4.2.1). Four modes:
//   CHARGING   — rub a polythene rod with a cloth: electrons (only the negative
//                charge) transfer from cloth to rod, leaving the rod negative and
//                the cloth positive.
//   FORCES     — two charged balls: like charges repel, unlike attract, with
//                force arrows; flip one charge's sign.
//   CONDUCTION — a conductor (free electrons that drift) vs an insulator (bound
//                electrons that stay put): touch a charge and watch it spread or
//                stay, explaining the electron model of conductors/insulators.
//   FIELD      — electric field patterns (point charge, charged sphere, parallel
//                plates) with the direction shown as the force on a + charge.
//
// Immersive 2D by the dimensionality doctrine (electrostatics is schematic).
// config: { mode, sign, pattern, material }

const PATTERNS = ['point', 'sphere', 'plates'];

export default function ChargeLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['charging', 'forces', 'conduction', 'field'].includes(config.mode) ? config.mode : 'charging');
    const [sign, setSign] = useState(config.sign === '+' ? '+' : config.sign === '-' ? '-' : '-'); // forces: right ball / field: source
    const [pattern, setPattern] = useState(PATTERNS.includes(config.pattern) ? config.pattern : 'point');
    const [material, setMaterial] = useState(config.material === 'insulator' ? 'insulator' : 'conductor');
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, sign, pattern, material };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, sign: st.current.sign, pattern: st.current.pattern, material: st.current.material }),
            setState: (s) => {
                if (['charging', 'forces', 'conduction', 'field'].includes(s?.mode)) setMode(s.mode);
                if (s?.sign === '+' || s?.sign === '-') setSign(s.sign);
                if (PATTERNS.includes(s?.pattern)) setPattern(s.pattern);
                if (s?.material === 'conductor' || s?.material === 'insulator') setMaterial(s.material);
            },
        });
    }, [onReady]); // eslint-disable-line

    const attract = mode === 'forces' && sign === '+'; // left ball is '-', so '+' right → unlike → attract

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
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', rose = '#FB7185', cyan = '#38BDF8';
            const POS = '#FB7185', NEG = '#4D9DFF';
            const S = st.current;
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#12161d'); bg.addColorStop(1, '#0b0e14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);

            const charge = (x, y, positive, r = 9) => {
                ctx.fillStyle = positive ? POS : NEG; ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.fill();
                ctx.strokeStyle = 'rgba(255,255,255,.5)'; ctx.lineWidth = 1; ctx.stroke();
                ctx.fillStyle = '#fff'; ctx.font = `700 ${r * 1.4}px Inter, sans-serif`; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                ctx.fillText(positive ? '+' : '−', x, y + 0.5); ctx.textBaseline = 'alphabetic';
            };
            const arrow = (x0, y0, x1, y1, col, wide = 2.4) => {
                ctx.strokeStyle = col; ctx.lineWidth = wide; ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke();
                const a = Math.atan2(y1 - y0, x1 - x0);
                ctx.fillStyle = col; ctx.beginPath(); ctx.moveTo(x1, y1);
                ctx.lineTo(x1 - Math.cos(a - 0.4) * 8, y1 - Math.sin(a - 0.4) * 8);
                ctx.lineTo(x1 - Math.cos(a + 0.4) * 8, y1 - Math.sin(a + 0.4) * 8); ctx.closePath(); ctx.fill();
            };

            if (S.mode === 'charging') {
                const cy = h * 0.42, rodX0 = w * 0.16, rodX1 = w * 0.62, rodY = cy;
                const rub = Math.sin(amb * 2.4) * (w * 0.08);
                // rod
                ctx.fillStyle = 'rgba(120,130,150,.5)'; ctx.strokeStyle = 'rgba(200,210,230,.7)'; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.roundRect(rodX0, rodY - 12, rodX1 - rodX0, 24, 8); ctx.fill(); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('polythene rod', (rodX0 + rodX1) / 2, rodY - 20);
                // cloth (rubbing)
                const clothX = (rodX0 + rodX1) / 2 + rub;
                ctx.fillStyle = 'rgba(180,140,90,.7)'; ctx.beginPath(); ctx.roundRect(clothX - 26, rodY - 26, 52, 20, 6); ctx.fill();
                ctx.fillStyle = faint; ctx.fillText('cloth', clothX, rodY - 30);
                // electrons transferring from cloth to rod (only negative charge moves)
                const nE = 7;
                for (let i = 0; i < nE; i++) {
                    const ph = (amb * 0.5 + i / nE) % 1;
                    const ex = clothX + (i - nE / 2) * 6, ey0 = rodY - 6, ey1 = rodY + 4;
                    if (ph < 0.5) { // riding on rod as accumulated charge
                        const rx = rodX0 + 18 + ((i * 53) % (rodX1 - rodX0 - 36));
                        ctx.fillStyle = NEG; ctx.font = '700 11px Inter, sans-serif';
                        ctx.fillText('−', rx, rodY + 4);
                    } else {
                        ctx.fillStyle = NEG; ctx.font = '700 11px Inter, sans-serif';
                        ctx.fillText('−', ex, ey0 + (ey1 - ey0) * ((ph - 0.5) * 2));
                    }
                }
                // net charge tags
                ctx.fillStyle = NEG; ctx.font = '700 12px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText('rod: negative (gained electrons)', rodX0, cy + h * 0.22);
                ctx.fillStyle = POS; ctx.fillText('cloth: positive (lost electrons)', rodX0, cy + h * 0.30);
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('only electrons (negative charge) move — the positive charges stay put', w * 0.5, h - 12);
            } else if (S.mode === 'forces') {
                const cy = h * 0.46, R = 15;
                const move = (attract ? -1 : 1) * (4 + Math.sin(amb * 2.2) * 3);
                const lx = w * 0.34 - move, rx = w * 0.66 + move;
                // field-ish connectors (light)
                // force arrows
                if (attract) { arrow(lx + R + 4, cy - 34, (lx + rx) / 2 - 4, cy - 34, acc); arrow(rx - R - 4, cy - 34, (lx + rx) / 2 + 4, cy - 34, acc); }
                else { arrow((lx + rx) / 2 - 4, cy - 34, lx + R + 4, cy - 34, rose); arrow((lx + rx) / 2 + 4, cy - 34, rx - R - 4, cy - 34, rose); }
                charge(lx, cy, false, R);  // left is negative
                charge(rx, cy, S.sign === '+', R);
                ctx.fillStyle = attract ? acc : rose; ctx.font = '700 13px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(attract ? 'unlike charges (− and +) ATTRACT' : 'like charges (− and −) REPEL', w * 0.5, cy - 58);
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif';
                ctx.fillText('charge is measured in coulombs (C)', w * 0.5, h - 12);
            } else if (S.mode === 'conduction') {
                const isCond = S.material === 'conductor';
                const bx0 = w * 0.2, bx1 = w * 0.8, by = h * 0.5, bh = h * 0.22;
                // the bar of material
                ctx.fillStyle = isCond ? 'rgba(120,150,190,.35)' : 'rgba(150,120,90,.3)';
                ctx.strokeStyle = isCond ? 'rgba(160,190,230,.7)' : 'rgba(200,170,130,.6)'; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.roundRect(bx0, by - bh / 2, bx1 - bx0, bh, 8); ctx.fill(); ctx.stroke();
                // fixed positive lattice (+)
                for (let i = 0; i < 9; i++) { const px = bx0 + 30 + i * ((bx1 - bx0 - 60) / 8); ctx.fillStyle = 'rgba(251,113,133,.5)'; ctx.font = '700 11px Inter'; ctx.textAlign = 'center'; ctx.fillText('+', px, by + 4); }
                // electrons: conductor → free (drift across); insulator → bound (jiggle in place)
                for (let i = 0; i < 9; i++) {
                    const home = bx0 + 30 + i * ((bx1 - bx0 - 60) / 8);
                    let ex;
                    if (isCond) { ex = bx0 + 24 + ((home - bx0 - 24 + amb * 40 + i * 20) % (bx1 - bx0 - 48)); }
                    else { ex = home + Math.sin(amb * 3 + i) * 2.2; }
                    ctx.fillStyle = NEG; ctx.beginPath(); ctx.arc(ex, by - 6, 3.4, 0, Math.PI * 2); ctx.fill();
                }
                ctx.fillStyle = ink; ctx.font = '700 12px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(isCond ? 'CONDUCTOR (e.g. copper): free electrons drift through it' : 'INSULATOR (e.g. plastic): electrons are bound, they cannot move', w * 0.5, by - bh / 2 - 12);
                ctx.fillStyle = faint; ctx.font = '600 9.5px Inter, sans-serif';
                ctx.fillText(isCond ? 'the free (delocalised) electrons carry charge → it conducts' : 'no free electrons to carry charge → it does not conduct', w * 0.5, by + bh / 2 + 20);
            } else {
                // FIELD patterns
                const cx = w * 0.5, cy = h * 0.46;
                const pos = S.sign !== '-'; // default + source unless '-'
                if (S.pattern === 'plates') {
                    const px0 = w * 0.24, px1 = w * 0.76, topY = cy - h * 0.24, botY = cy + h * 0.24;
                    ctx.fillStyle = POS; ctx.fillRect(px0, topY - 6, px1 - px0, 6);
                    ctx.fillStyle = NEG; ctx.fillRect(px0, botY, px1 - px0, 6);
                    ctx.fillStyle = '#fff'; ctx.font = '700 12px Inter'; ctx.textAlign = 'center';
                    ctx.fillText('+ + + + + + +', (px0 + px1) / 2, topY - 10); ctx.fillText('− − − − − − −', (px0 + px1) / 2, botY + 18);
                    for (let i = 0; i <= 8; i++) { const x = px0 + 14 + i * ((px1 - px0 - 28) / 8); arrow(x, topY, x, botY, 'rgba(120,200,255,.85)', 1.8); }
                    ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif';
                    ctx.fillText('parallel plates → uniform field (evenly spaced), pointing + to −', w * 0.5, h - 12);
                } else {
                    const r0 = S.pattern === 'sphere' ? 24 : 10;
                    // source
                    if (S.pattern === 'sphere') { ctx.fillStyle = pos ? 'rgba(251,113,133,.4)' : 'rgba(77,157,255,.4)'; ctx.strokeStyle = pos ? POS : NEG; ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(cx, cy, r0, 0, Math.PI * 2); ctx.fill(); ctx.stroke(); ctx.fillStyle = '#fff'; ctx.font = '700 16px Inter'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(pos ? '+' : '−', cx, cy); ctx.textBaseline = 'alphabetic'; }
                    else charge(cx, cy, pos, 11);
                    const NL = 12;
                    for (let i = 0; i < NL; i++) {
                        const a = (i / NL) * Math.PI * 2;
                        const sx = cx + Math.cos(a) * r0, sy = cy + Math.sin(a) * r0;
                        const ex = cx + Math.cos(a) * Math.min(w, h) * 0.42, ey = cy + Math.sin(a) * Math.min(w, h) * 0.42;
                        if (pos) arrow(sx, sy, ex, ey, 'rgba(120,200,255,.8)', 1.6);
                        else arrow(ex, ey, sx, sy, 'rgba(120,200,255,.8)', 1.6);
                    }
                    ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                    ctx.fillText(`${S.pattern === 'sphere' ? 'charged sphere' : 'point charge'} → radial field; arrows show the force on a + charge (${pos ? 'away from +' : 'towards −'})`, w * 0.5, h - 12);
                }
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const badge = { charging: 'Charging by friction · electron transfer', forces: 'Charges · attract & repel', conduction: 'Conductors vs insulators · electron model', field: 'Electric field patterns' }[mode];

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
                    <button className={'cw-btn ' + (mode === 'charging' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('charging')}>✋ Charging</button>
                    <button className={'cw-btn ' + (mode === 'forces' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('forces')}>± Forces</button>
                    <button className={'cw-btn ' + (mode === 'conduction' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('conduction')}>🔌 Conduction</button>
                    <button className={'cw-btn ' + (mode === 'field' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('field')}>➰ Field</button>
                </div>

                {mode === 'charging' && (
                    <>
                        <Stat label="charging a rod by friction" value="electrons transfer" tone="acc"
                              sub={<>rubbing moves <b>electrons</b> from the cloth to the rod: the rod becomes <b>negative</b> (gained electrons), the cloth <b>positive</b> (lost electrons). Only <b>negative</b> charge moves.</>} />
                        <Flag kind="neutral">
                            Rubbing two insulators together charges them by <b>friction</b>. Charge is measured in <b>coulombs (C)</b>.
                            Only <b>electrons (negative charge)</b> are transferred — the positive nuclei stay fixed. Whichever material
                            <b> gains</b> electrons becomes <b>negative</b>; the one that <b>loses</b> them becomes <b>positive</b> (here,
                            polythene gains electrons and goes negative).
                        </Flag>
                    </>
                )}

                {mode === 'forces' && (
                    <>
                        <Stat label={attract ? 'unlike charges' : 'like charges'} value={attract ? 'attract' : 'repel'} tone={attract ? 'acc' : 'warn'}
                              sub={<><b>Like</b> charges (＋＋ or −−) <b>repel</b>; <b>unlike</b> charges (＋−) <b>attract</b>.</>} />
                        <button className="cw-btn cw-btn-ghost" onClick={() => setSign(sign === '+' ? '-' : '+')}>↺ Flip the right-hand charge ({sign === '+' ? 'now +' : 'now −'})</button>
                        <Flag kind="neutral">
                            There are <b>two kinds of charge</b>, positive and negative, measured in <b>coulombs (C)</b>. The rule is
                            like magnetic poles: <b>like charges repel, unlike charges attract</b>. Flip the right charge to switch
                            between attraction and repulsion.
                        </Flag>
                    </>
                )}

                {mode === 'conduction' && (
                    <>
                        <Stat label={material === 'conductor' ? 'electrical conductor' : 'electrical insulator'} value={material === 'conductor' ? 'free electrons' : 'bound electrons'} tone="acc"
                              sub={material === 'conductor' ? <>in a <b>conductor</b> (metal), some electrons are <b>free to move</b> — they drift through it and carry charge</> : <>in an <b>insulator</b> (plastic, rubber), electrons are <b>bound</b> to their atoms and cannot move, so charge stays put</>} />
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (material === 'conductor' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMaterial('conductor')}>Conductor</button>
                            <button className={'cw-btn ' + (material === 'insulator' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMaterial('insulator')}>Insulator</button>
                        </div>
                        <Flag kind="neutral">
                            <b>Conductors</b> (metals like copper, and graphite) let charge flow because they have <b>free electrons</b>;
                            <b> insulators</b> (plastic, rubber, glass, dry wood) do not, because their electrons are <b>held tightly</b>
                            to their atoms. You can test which is which by seeing whether charge flows through it (e.g. in a simple
                            circuit with a lamp).
                        </Flag>
                    </>
                )}

                {mode === 'field' && (
                    <>
                        <Stat label="electric field" value="a region where a charge feels a force" tone="acc"
                              sub={<>the <b>field lines</b> show the direction of the <b>force on a positive charge</b>: <b>away from +</b>, <b>towards −</b>. Where lines are closer, the field is stronger.</>} />
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (pattern === 'point' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setPattern('point')}>Point charge</button>
                            <button className={'cw-btn ' + (pattern === 'sphere' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setPattern('sphere')}>Charged sphere</button>
                            <button className={'cw-btn ' + (pattern === 'plates' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setPattern('plates')}>Parallel plates</button>
                        </div>
                        {pattern !== 'plates' && (
                            <button className="cw-btn cw-btn-ghost" onClick={() => setSign(sign === '+' ? '-' : '+')}>source charge: {sign === '+' ? '+ (field points out)' : '− (field points in)'}</button>
                        )}
                        <Flag kind="neutral">
                            An <b>electric field</b> is a region in which an electric charge experiences a <b>force</b>. The
                            <b> direction of a field line</b> is the direction of the force on a <b>positive</b> charge — so lines point
                            <b> away from a + charge</b> and <b>towards a − charge</b>. A <b>point charge</b> and a <b>charged sphere</b>
                            give a <b>radial</b> field; <b>parallel plates</b> give a <b>uniform</b> field (evenly spaced lines).
                        </Flag>
                    </>
                )}

                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, sign, pattern, material })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
