import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag, Slider } from '../primitives.jsx';

// ── Widget: weigh_lab ────────────────────────────────────────────────────────
// Bespoke Mass & Weight hero simulator (5054 · 1.3). One sealed crate is
// examined on three instruments AT ONCE — a beam balance against standard
// masses, an electronic balance, and a hanging force meter — while a WORLD
// selector changes the local gravitational field strength (and the sky behind
// it). The beam stays level and the kilograms stay put on every world; only the
// force meter's newtons move. Field strengths are FICTIONAL given data (A/B/C).
//
// Lake-bar immersion: each world has its own sky, horizon and moon/sun so the
// change of place is felt. Instrument logic and config unchanged.
//
// config: { mass, world } · Notes contract: getState/setState.

const WORLDS = [
    { key: 'A', label: 'World A', g: 10, sky: ['#1b2b46', '#3f6187', '#b98a5e'], body: '#ffe6bf', bodyR: 16, stars: 0 },
    { key: 'B', label: 'World B', g: 4, sky: ['#2a1a3c', '#6a3f78', '#c88bb0'], body: '#f6c7e0', bodyR: 12, stars: 0 },
    { key: 'C', label: 'World C', g: 1.6, sky: ['#070b14', '#141c2a', '#2a323e'], body: '#dfe6ee', bodyR: 20, stars: 34 },
];

export default function WeighLab({ config = {}, onReady, onAddToNote }) {
    const [mass, setMass] = useState(Math.max(1, Math.min(10, config.mass ?? 6)));
    const [world, setWorld] = useState(WORLDS.some((w) => w.key === config.world) ? config.world : 'A');
    const cvRef = useRef(null);
    const animRef = useRef({ needle: 0, pan: 0 });
    const st = useRef({}); st.current = { mass, world };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mass: st.current.mass, world: st.current.world }),
            setState: (s) => {
                if (typeof s?.mass === 'number') setMass(Math.max(1, Math.min(10, s.mass)));
                if (s?.world && WORLDS.some((w) => w.key === s.world)) setWorld(s.world);
            },
        });
    }, [onReady]); // eslint-disable-line

    const g = WORLDS.find((w) => w.key === world).g;
    const weight = mass * g;

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf;
        const draw = () => {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
            const cyan = '#38BDF8', amber = '#FBBF24';
            const { mass: m, world: wd } = st.current;
            const W = WORLDS.find((x) => x.key === wd);
            const gg = W.g, Wn = m * gg;

            const a = animRef.current;
            a.needle += ((Wn / 100) - a.needle) * 0.12;
            a.pan += (0 - a.pan) * 0.1;

            // ── World backdrop ──
            const horizonY = h * 0.60;
            const sky = ctx.createLinearGradient(0, 0, 0, horizonY);
            sky.addColorStop(0, W.sky[0]); sky.addColorStop(0.65, W.sky[1]); sky.addColorStop(1, W.sky[2]);
            ctx.fillStyle = sky; ctx.fillRect(0, 0, w, horizonY);
            for (let i = 0; i < W.stars; i++) {
                const sx = (Math.sin(i * 91.7) * 0.5 + 0.5) * w, sy = (Math.sin(i * 33.3) * 0.5 + 0.5) * horizonY * 0.9;
                ctx.fillStyle = `rgba(255,255,255,${0.3 + (Math.sin(i * 7.1) * 0.5 + 0.5) * 0.5})`;
                ctx.fillRect(sx, sy, 1.4, 1.4);
            }
            // celestial body with glow
            const bx0 = w * 0.82, by0 = horizonY * 0.34;
            const bg = ctx.createRadialGradient(bx0, by0, 2, bx0, by0, W.bodyR * 3);
            bg.addColorStop(0, W.body); bg.addColorStop(0.3, 'rgba(255,255,255,.15)'); bg.addColorStop(1, 'rgba(255,255,255,0)');
            ctx.fillStyle = bg; ctx.beginPath(); ctx.arc(bx0, by0, W.bodyR * 3, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = W.body; ctx.beginPath(); ctx.arc(bx0, by0, W.bodyR, 0, Math.PI * 2); ctx.fill();
            // ground
            const grd = ctx.createLinearGradient(0, horizonY, 0, h);
            grd.addColorStop(0, 'rgba(20,24,30,.9)'); grd.addColorStop(1, 'rgba(12,15,20,1)');
            ctx.fillStyle = grd; ctx.fillRect(0, horizonY, w, h - horizonY);
            ctx.strokeStyle = 'rgba(255,255,255,.08)'; ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(0, horizonY); ctx.lineTo(w, horizonY); ctx.stroke();

            const colW = w / 3, baseY = h - 40;
            ctx.font = '600 10.5px Inter, sans-serif';

            // ── 1 · Beam balance ──
            const bx = colW * 0.5, by = baseY - 60;
            ctx.fillStyle = ink; ctx.textAlign = 'center';
            ctx.fillText('beam balance — COMPARES', bx, 22);
            ctx.strokeStyle = '#9aa3ad'; ctx.lineWidth = 3;
            ctx.beginPath(); ctx.moveTo(bx, by); ctx.lineTo(bx, baseY); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(bx - 26, baseY); ctx.lineTo(bx + 26, baseY); ctx.stroke();
            ctx.save(); ctx.translate(bx, by); ctx.rotate(a.pan);
            ctx.lineWidth = 3.4; ctx.beginPath(); ctx.moveTo(-64, 0); ctx.lineTo(64, 0); ctx.stroke();
            for (const s of [-1, 1]) {
                ctx.lineWidth = 1.4;
                ctx.beginPath(); ctx.moveTo(64 * s, 0); ctx.lineTo(64 * s - 10, 26); ctx.moveTo(64 * s, 0); ctx.lineTo(64 * s + 10, 26); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(64 * s - 14, 26); ctx.lineTo(64 * s + 14, 26); ctx.stroke();
            }
            ctx.fillStyle = amber; ctx.fillRect(-64 - 10, 26 - 14, 20, 13);
            ctx.fillStyle = '#c9d2dc';
            const nStd = Math.max(1, Math.round(m));
            for (let i = 0; i < Math.min(nStd, 5); i++) ctx.fillRect(64 - 12 + (i % 3) * 9, 26 - 6 - Math.floor(i / 3) * 7, 7, 5);
            ctx.restore();
            ctx.fillStyle = acc; ctx.textAlign = 'center';
            ctx.fillText(`level against ${m.toFixed(1)} kg of standards`, bx, baseY + 18);
            ctx.fillStyle = faint; ctx.fillText('on every world', bx, baseY + 32);

            // ── 2 · Electronic balance ──
            const ex = colW * 1.5;
            ctx.fillStyle = ink; ctx.fillText('electronic balance — DETERMINES MASS', ex, 22);
            const ebG = ctx.createLinearGradient(0, baseY - 34, 0, baseY);
            ebG.addColorStop(0, '#e9edf2'); ebG.addColorStop(1, '#b7bec7');
            ctx.fillStyle = ebG; ctx.beginPath(); ctx.roundRect(ex - 52, baseY - 34, 104, 34, 6); ctx.fill();
            ctx.fillStyle = '#cfd5dc'; ctx.beginPath(); ctx.ellipse(ex, baseY - 34, 40, 5, 0, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = amber; ctx.fillRect(ex - 16, baseY - 34 - 22, 32, 21);
            ctx.fillStyle = '#0d130f'; ctx.beginPath(); ctx.roundRect(ex - 40, baseY - 26, 80, 18, 4); ctx.fill();
            ctx.fillStyle = '#4ef0a8'; ctx.font = '700 13px "JetBrains Mono", monospace';
            ctx.fillText(`${m.toFixed(2)} kg`, ex, baseY - 12);
            ctx.font = '600 10.5px Inter, sans-serif'; ctx.fillStyle = faint;
            ctx.fillText('same reading on every world', ex, baseY + 18);

            // ── 3 · Force meter ──
            const fx = colW * 2.5, top = 34;
            ctx.fillStyle = ink; ctx.fillText('force meter — MEASURES WEIGHT', fx, 22);
            ctx.strokeStyle = '#9aa3ad'; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(fx, top); ctx.lineTo(fx, top + 10); ctx.stroke();
            const bodyH = 92;
            const fmG = ctx.createLinearGradient(fx - 16, 0, fx + 16, 0);
            fmG.addColorStop(0, 'rgba(255,255,255,.18)'); fmG.addColorStop(0.5, 'rgba(160,200,175,.06)'); fmG.addColorStop(1, 'rgba(255,255,255,.14)');
            ctx.fillStyle = fmG; ctx.beginPath(); ctx.roundRect(fx - 16, top + 10, 32, bodyH, 8); ctx.fill();
            ctx.strokeStyle = '#9aa3ad'; ctx.beginPath(); ctx.roundRect(fx - 16, top + 10, 32, bodyH, 8); ctx.stroke();
            for (let i = 0; i <= 5; i++) {
                const sy = top + 18 + (bodyH - 16) * (i / 5);
                ctx.strokeStyle = line; ctx.beginPath(); ctx.moveTo(fx - 9, sy); ctx.lineTo(fx + 3, sy); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 7.5px "JetBrains Mono", monospace'; ctx.textAlign = 'left';
                ctx.fillText(String(i * 20), fx + 6, sy + 2.5);
            }
            const ny = top + 18 + (bodyH - 16) * Math.min(1, a.needle);
            ctx.strokeStyle = cyan; ctx.lineWidth = 2.4;
            ctx.beginPath(); ctx.moveTo(fx - 12, ny); ctx.lineTo(fx + 4, ny); ctx.stroke();
            const stretch = 12 + 46 * Math.min(1, a.needle);
            ctx.strokeStyle = '#c9d2dc'; ctx.lineWidth = 1.6; ctx.beginPath();
            ctx.moveTo(fx, top + 10 + bodyH);
            const coils = 7;
            for (let i = 0; i <= coils; i++) ctx.lineTo(fx + (i % 2 === 0 ? -7 : 7), top + 10 + bodyH + (stretch * i) / coils);
            ctx.lineTo(fx, top + 10 + bodyH + stretch + 4); ctx.stroke();
            ctx.fillStyle = amber; ctx.fillRect(fx - 15, top + 14 + bodyH + stretch, 30, 20);
            ctx.fillStyle = cyan; ctx.font = '700 13px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            ctx.fillText(`${Wn.toFixed(1)} N`, fx, top + 14 + bodyH + stretch + 36);
            ctx.font = '600 10.5px Inter, sans-serif'; ctx.fillStyle = faint;
            ctx.fillText('this reading belongs to the place', fx, baseY + 18);

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 9' }}>
                    <span className="cw-badge">One crate · three instruments · any world</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="local field strength (given)" value={`g = ${g}`} unit="N/kg" tone="acc"
                      sub={<>crate: mass <b>{mass.toFixed(1)} kg</b> → weight <b>{weight.toFixed(1)} N</b> here</>} />
                <div className="cw-btnrow">
                    {WORLDS.map((wd) => (
                        <button key={wd.key} className={'cw-btn ' + (wd.key === world ? 'cw-btn-save' : 'cw-btn-ghost')}
                                onClick={() => setWorld(wd.key)}>
                            {wd.label} · {wd.g} N/kg
                        </button>
                    ))}
                </div>
                <Slider label="crate mass" value={mass} min={1} max={10} step={0.5}
                        onChange={(val) => setMass(+val)} format={(val) => `${(+val).toFixed(1)} kg`} />
                <Flag kind="neutral">
                    Switch worlds and watch what moves: the <b>beam stays level</b> (both pans' weights change together, so the
                    comparison holds), the <b>kilograms stay put</b> — but the <b>force meter's newtons follow the place</b>.
                    Mass is the crate's; weight is the world's pull on it.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mass, world })}>📌 Save this setup to my notes</button>
                )}
            </div>
        </div>
    );
}
