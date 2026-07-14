import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag } from '../primitives.jsx';

// ── Widget: rutherford_lab ───────────────────────────────────────────────────
// Bespoke 5.1.1 hero (immersive 2D). The nuclear atom + alpha-scattering.
//   ATOM       — the nuclear model: a tiny central nucleus (protons + neutrons)
//                with electrons in orbit, and mostly EMPTY SPACE between them.
//   SCATTERING — Rutherford's alpha-scattering experiment: alpha particles fired
//                at gold foil. MOST pass straight through (empty space); a FEW are
//                deflected; a VERY FEW bounce almost straight back (a head-on hit
//                on the tiny, dense, positively charged nucleus). The three
//                observations map to the three conclusions.
// config: { mode }

export default function RutherfordLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['atom', 'scattering'].includes(config.mode) ? config.mode : 'scattering');
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode }),
            setState: (s) => { if (['atom', 'scattering'].includes(s?.mode)) setMode(s.mode); },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, t0 = performance.now();
        // scattering: persistent alpha particles with impact parameters
        let alphas = [];
        const spawn = (w, h, ny, nucleusY) => {
            const b = (Math.random() - 0.5) * h * 0.7;              // impact parameter (miss distance)
            return { x: -20, y: h / 2 + b, b, vx: 2.2 + Math.random(), done: false };
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
            ctx.textAlign = 'center'; ctx.lineCap = 'round';

            if (S.mode === 'atom') {
                const cx = w * 0.42, cy = h * 0.46;
                // electron shells
                ctx.strokeStyle = 'rgba(120,180,255,.28)'; ctx.lineWidth = 1.2;
                [70, 115, 160].forEach((r) => { ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.stroke(); });
                // electrons orbiting
                const shells = [[70, 2, 0.9], [115, 3, -0.6], [160, 3, 0.4]];
                shells.forEach(([r, n, sp]) => { for (let k = 0; k < n; k++) { const a = amb * sp + (k / n) * Math.PI * 2; const ex = cx + Math.cos(a) * r, ey = cy + Math.sin(a) * r; ctx.fillStyle = cyan; ctx.beginPath(); ctx.arc(ex, ey, 5, 0, Math.PI * 2); ctx.fill(); ctx.fillStyle = '#0b0e14'; ctx.font = '700 8px Inter'; ctx.fillText('−', ex, ey + 3); } });
                // nucleus: cluster of protons (red +) and neutrons (grey)
                const nuc = [[0, 0, 1], [7, -4, 0], [-6, 5, 1], [4, 6, 0], [-5, -5, 1], [8, 3, 0]];
                nuc.forEach(([dx, dy, isP]) => { ctx.fillStyle = isP ? rose : '#9aa4b2'; ctx.beginPath(); ctx.arc(cx + dx, cy + dy, 6.5, 0, Math.PI * 2); ctx.fill(); ctx.fillStyle = '#0b0e14'; ctx.font = '700 8px Inter'; ctx.fillText(isP ? '+' : '', cx + dx, cy + dy + 3); });
                ctx.strokeStyle = rose; ctx.lineWidth = 1; ctx.beginPath(); ctx.arc(cx, cy, 15, 0, Math.PI * 2); ctx.stroke();
                // labels
                ctx.fillStyle = rose; ctx.font = '700 11px Inter'; ctx.textAlign = 'left'; ctx.fillText('nucleus (+)', cx + 26, cy - 20);
                ctx.strokeStyle = rose; ctx.beginPath(); ctx.moveTo(cx + 16, cy - 8); ctx.lineTo(cx + 24, cy - 18); ctx.stroke();
                ctx.fillStyle = cyan; ctx.fillText('electrons (−) in orbit', cx + 120, cy + 150);
                ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.textAlign = 'center';
                ctx.fillText('protons (+) and neutrons in a tiny nucleus; electrons orbit far out — the atom is MOSTLY EMPTY SPACE', w * 0.5, h - 12);
            } else {
                // SCATTERING: gold foil = a vertical line of nuclei; alphas from the left
                const foilX = w * 0.6, nucY = h / 2;
                // foil nuclei
                ctx.fillStyle = 'rgba(251,191,36,.5)';
                for (let y = h * 0.16; y < h * 0.86; y += 26) { ctx.beginPath(); ctx.arc(foilX, y, 3.5, 0, Math.PI * 2); ctx.fill(); }
                const mainNucY = nucY; // the nucleus that causes back-scatter (aligned with beam centre)
                ctx.fillStyle = amber; ctx.beginPath(); ctx.arc(foilX, mainNucY, 6, 0, Math.PI * 2); ctx.fill();
                ctx.strokeStyle = 'rgba(251,191,36,.4)'; ctx.lineWidth = 1; ctx.beginPath(); ctx.arc(foilX, mainNucY, 12, 0, Math.PI * 2); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.fillText('gold foil (nuclei)', foilX, h * 0.12);
                // maintain a pool of alphas
                while (alphas.length < 26) alphas.push(spawn(w, h));
                let through = 0, deflected = 0, back = 0;
                alphas.forEach((p) => {
                    // near the foil, deflect by the Coulomb repulsion from the aligned nucleus
                    const dxn = foilX - p.x, dyn = mainNucY - p.y;
                    const closeB = Math.abs(p.y - mainNucY);
                    if (p.x < foilX - 4) {
                        p.x += p.vx;
                        // small-angle deflection if it passes near the main nucleus
                        if (p.x > foilX - 90 && closeB < 40) { const f = (40 - closeB) / 40; p.y += Math.sign(p.y - mainNucY || 1) * f * f * 1.4; }
                    } else {
                        // reached foil: decide fate by impact parameter to the main nucleus
                        if (closeB < 7) { p.back = true; }        // head-on → bounce back
                        else if (closeB < 40) { p.defl = true; p.x += p.vx; p.y += Math.sign(p.y - mainNucY || 1) * 2.4; } // large deflect
                        else { p.x += p.vx; }                     // straight through
                        if (p.back) { p.x -= p.vx * 1.1; p.y += Math.sign(p.y - mainNucY || 1) * 2.2; }
                    }
                    // draw
                    const col = p.back ? rose : (p.defl ? amber : acc);
                    ctx.fillStyle = col; ctx.beginPath(); ctx.arc(p.x, p.y, 3, 0, Math.PI * 2); ctx.fill();
                    // tally near-final
                    if (p.back) back++; else if (p.defl) deflected++; else through++;
                    if (p.x < -30 || p.x > w + 30 || p.y < -30 || p.y > h + 30) { Object.assign(p, spawn(w, h)); }
                });
                // beam source label
                ctx.fillStyle = acc; ctx.font = '700 10px Inter'; ctx.textAlign = 'left';
                ctx.fillText('α source', 6, h / 2 - 10);
                ctx.fillStyle = ink; ctx.textAlign = 'center'; ctx.font = '600 10px Inter';
                ctx.fillText('MOST pass straight through (empty space) · a FEW deflect · a VERY FEW bounce back (hit the nucleus)', w * 0.5, h - 12);
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
                    <span className="cw-badge">{mode === 'atom' ? 'The nuclear atom' : 'Alpha-scattering experiment'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'atom' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('atom')}>⚛ The atom</button>
                    <button className={'cw-btn ' + (mode === 'scattering' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('scattering')}>↯ Scattering</button>
                </div>
                {mode === 'atom' ? (
                    <>
                        <Stat label="the nuclear model of the atom" value="tiny +nucleus, orbiting −electrons" tone="acc"
                              sub={<>a small, dense, <b>positively charged nucleus</b> (protons + neutrons) sits at the centre, with <b>negatively charged electrons</b> orbiting far out. Almost all the atom is <b>empty space</b>.</>} />
                        <Flag kind="neutral">
                            An atom has a tiny central <b>nucleus</b> that is <b>positively charged</b> (it contains the <b>protons</b>), with
                            <b> negatively charged electrons</b> moving in <b>orbits</b> around it. The nucleus is <b>thousands of times smaller</b>
                            than the atom, so the atom is <b>mostly empty space</b> — the electrons are held in their orbits by the electrostatic
                            attraction to the positive nucleus.
                        </Flag>
                    </>
                ) : (
                    <>
                        <Stat label="Rutherford's alpha-scattering" value="most through · few deflected · rare bounce-back" tone="acc"
                              sub={<>firing <b>alpha particles</b> at thin gold foil: <b>most pass straight through</b>, a <b>few</b> are deflected, and a <b>very few</b> bounce almost <b>straight back</b>.</>} />
                        <Flag kind="neutral">
                            In the <b>alpha-scattering experiment</b>, a beam of alpha particles is fired at very thin gold foil. Three
                            observations give three conclusions:
                            <br/>• <b>Most pass straight through</b> → the atom is <b>mostly empty space</b>.
                            <br/>• A <b>very few bounce almost straight back</b> → there is a <b>tiny, dense nucleus</b> that holds <b>most of the mass</b>.
                            <br/>• The alphas (which are <b>positive</b>) are repelled → the nucleus is <b>positively charged</b>.
                        </Flag>
                    </>
                )}
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
