import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: radiation_safety_lab ─────────────────────────────────────────────
// Bespoke 5.2.6 hero (immersive 2D). Effects of ionising radiation + safe handling.
//   EFFECTS: ionising radiation striking living cells — ionising atoms inside them,
//            causing cell death, mutations and cancer (qualitative, dose-dependent).
//   PROTECTION: a worker and a source, with sliders for exposure TIME, DISTANCE and
//            SHIELDING; a live relative-dose meter falls as time↓, distance↑, shield↑.
// config: { mode, time, distance, shield }

const SHIELDS = [
    { key: 'none', label: 'none', factor: 1.0, desc: 'no shielding' },
    { key: 'lead-apron', label: 'lead apron', factor: 0.35, desc: 'a lead apron' },
    { key: 'lead-wall', label: 'lead / concrete wall', factor: 0.08, desc: 'a thick lead or concrete wall' },
];

export default function RadiationSafetyLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['effects', 'protection'].includes(config.mode) ? config.mode : 'protection');
    const [time, setTime] = useState(Number.isFinite(config.time) ? config.time : 30);      // minutes
    const [distance, setDistance] = useState(Number.isFinite(config.distance) ? config.distance : 2); // metres
    const [shieldIdx, setShieldIdx] = useState(Number.isFinite(config.shield) ? config.shield : 0);
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, time, distance, shieldIdx };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, time: st.current.time, distance: st.current.distance, shield: st.current.shieldIdx }),
            setState: (s) => { if (['effects', 'protection'].includes(s?.mode)) setMode(s.mode); if (Number.isFinite(s?.time)) setTime(s.time); if (Number.isFinite(s?.distance)) setDistance(s.distance); if (Number.isFinite(s?.shield)) setShieldIdx(s.shield); },
        });
    }, [onReady]); // eslint-disable-line

    const shield = SHIELDS[shieldIdx] || SHIELDS[0];
    // relative dose (arbitrary units): grows with time, falls with distance, cut by shield
    const dose = (time / 30) * (1 / (distance * distance)) * shield.factor * 100;

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, particles = [];
        const draw = (now) => {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight;
            if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const faint = cssVar('--ink-faint', '#68877A'), ink = cssVar('--ink-soft', '#A6C4B3');
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', rose = '#FB7185', cyan = '#38BDF8', violet = '#a78bfa';
            const S = st.current, t = now / 1000;
            const bg = ctx.createLinearGradient(0, 0, 0, h); bg.addColorStop(0, '#0f1420'); bg.addColorStop(1, '#0a0d14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);
            ctx.textAlign = 'center';

            const sourceStar = (x, y, glow) => {
                const g = ctx.createRadialGradient(x, y, 0, x, y, 22); g.addColorStop(0, `rgba(251,191,36,${0.8 * glow})`); g.addColorStop(1, 'rgba(251,191,36,0)');
                ctx.fillStyle = g; ctx.beginPath(); ctx.arc(x, y, 22, 0, 7); ctx.fill();
                ctx.fillStyle = amber; ctx.beginPath(); ctx.arc(x, y, 7, 0, 7); ctx.fill();
                ctx.strokeStyle = amber; ctx.lineWidth = 1.5; for (let a = 0; a < 6; a++) { const an = a * Math.PI / 3 + t * 0.5; ctx.beginPath(); ctx.moveTo(x + Math.cos(an) * 9, y + Math.sin(an) * 9); ctx.lineTo(x + Math.cos(an) * 13, y + Math.sin(an) * 13); ctx.stroke(); }
            };

            if (S.mode === 'effects') {
                // a tissue of cells on the right; radiation streams from the left ionising them
                const sx = w * 0.12, sy = h * 0.5; sourceStar(sx, sy, 1);
                ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.fillText('ionising source', sx, sy + 34);
                const cols = 6, rows = 5, gx0 = w * 0.42, gy0 = h * 0.18, cell = Math.min((w * 0.5) / cols, (h * 0.6) / rows);
                // spawn ionising rays
                if (Math.random() < 0.5) particles.push({ x: sx, y: sy + (Math.random() - 0.5) * 40, vx: 260, vy: (Math.random() - 0.5) * 40, life: 1 });
                particles.forEach(p => { p.x += p.vx * 0.016; p.y += p.vy * 0.016; ctx.strokeStyle = violet; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(p.x - 6, p.y); ctx.lineTo(p.x, p.y); ctx.stroke(); });
                particles = particles.filter(p => p.x < w);
                // draw cells (deterministic states by index)
                for (let i = 0; i < cols * rows; i++) {
                    const r = Math.floor(i / cols), cN = i % cols; const x = gx0 + cN * cell + cell / 2, y = gy0 + r * cell + cell / 2;
                    const stateHash = (i * 37) % 100;
                    let col = acc, lbl = null;
                    if (stateHash < 16) { col = 'rgba(120,130,145,0.4)'; lbl = 'dead'; }       // cell death
                    else if (stateHash < 30) { col = amber; lbl = 'mutated'; }                   // mutation
                    else if (stateHash < 38) { col = rose; lbl = 'cancer'; }                     // cancer
                    ctx.fillStyle = col; ctx.beginPath(); ctx.arc(x, y, cell * 0.32, 0, 7); ctx.fill();
                    ctx.strokeStyle = 'rgba(255,255,255,0.12)'; ctx.lineWidth = 1; ctx.stroke();
                    // nucleus
                    ctx.fillStyle = 'rgba(255,255,255,0.25)'; ctx.beginPath(); ctx.arc(x, y, cell * 0.12, 0, 7); ctx.fill();
                }
                ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.fillText('living tissue (cells)', gx0 + cols * cell / 2, gy0 - 6);
                // legend
                const ly = h - 26; ctx.textAlign = 'left'; ctx.font = '600 10px Inter';
                ctx.fillStyle = 'rgba(120,130,145,0.8)'; ctx.fillText('● cell death', w * 0.42, ly);
                ctx.fillStyle = amber; ctx.fillText('● mutation', w * 0.6, ly);
                ctx.fillStyle = rose; ctx.fillText('● cancer', w * 0.78, ly);
                ctx.textAlign = 'center'; ctx.fillStyle = ink; ctx.font = '700 12px Inter';
                ctx.fillText('ionising radiation damages cells → cell death, mutations, cancer', w / 2, h - 10);
            }

            else { // protection
                const sx = w * 0.1, sy = h * 0.5; sourceStar(sx, sy, 1);
                ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.fillText('source', sx, sy + 34);
                // person on the right, at a distance scaled by S.distance (1..6 m -> x)
                const px = w * (0.28 + 0.62 * Math.min(1, (S.distance - 1) / 5));
                // stick person
                ctx.strokeStyle = ink; ctx.lineWidth = 2.4; ctx.beginPath();
                ctx.arc(px, sy - 26, 7, 0, 7); ctx.moveTo(px, sy - 19); ctx.lineTo(px, sy + 6); ctx.moveTo(px - 10, sy - 10); ctx.lineTo(px + 10, sy - 10); ctx.moveTo(px, sy + 6); ctx.lineTo(px - 8, sy + 22); ctx.moveTo(px, sy + 6); ctx.lineTo(px + 8, sy + 22); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.fillText('worker', px, sy + 38);
                // shield wall (between source and person) if any
                const wx = (sx + px) / 2;
                if (S.shieldIdx > 0) {
                    const sh = SHIELDS[S.shieldIdx]; const thick = S.shieldIdx === 2 ? 14 : 7;
                    ctx.fillStyle = S.shieldIdx === 2 ? 'rgba(120,130,145,0.85)' : 'rgba(160,170,185,0.7)';
                    ctx.fillRect(wx - thick / 2, sy - 40, thick, 80);
                    ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.fillText(sh.label, wx, sy - 46);
                }
                // radiation arrows from source toward person; density ~ time, attenuated by shield beyond wall
                const nRays = Math.max(3, Math.round((S.time / 60) * 12));
                for (let i = 0; i < nRays; i++) {
                    const frac = ((t * 0.4 + i / nRays) % 1);
                    const rx = sx + (px - sx) * frac, ry = sy + Math.sin(i * 1.7) * 26 * frac;
                    const past = rx > wx; const blocked = past && S.shieldIdx > 0 && Math.random() > SHIELDS[S.shieldIdx].factor + 0.15;
                    if (blocked) continue;
                    ctx.fillStyle = past && S.shieldIdx > 0 ? 'rgba(167,139,250,0.4)' : violet;
                    ctx.beginPath(); ctx.arc(rx, ry, 2.6, 0, 7); ctx.fill();
                }
                // distance bracket
                ctx.strokeStyle = 'rgba(56,189,248,0.5)'; ctx.setLineDash([3, 3]); ctx.lineWidth = 1;
                ctx.beginPath(); ctx.moveTo(sx, sy + 50); ctx.lineTo(px, sy + 50); ctx.stroke(); ctx.setLineDash([]);
                ctx.fillStyle = cyan; ctx.font = '600 10px Inter'; ctx.fillText(`${S.distance.toFixed(1)} m`, (sx + px) / 2, sy + 62);

                // dose bar
                const d = (S.time / 30) * (1 / (S.distance * S.distance)) * SHIELDS[S.shieldIdx].factor * 100;
                const barMax = 120, dw = Math.min(1, d / barMax) * (w * 0.7);
                ctx.fillStyle = 'rgba(255,255,255,0.08)'; ctx.fillRect(w * 0.15, h - 26, w * 0.7, 12);
                ctx.fillStyle = d > 60 ? rose : d > 20 ? amber : acc; ctx.fillRect(w * 0.15, h - 26, dw, 12);
                ctx.fillStyle = ink; ctx.font = '700 11px Inter'; ctx.textAlign = 'left';
                ctx.fillText(`relative dose: ${d.toFixed(0)}`, w * 0.15, h - 32);
                ctx.textAlign = 'center'; ctx.fillStyle = faint; ctx.font = '600 10px Inter';
                ctx.fillText('less time · more distance · more shielding  →  lower dose', w / 2, h - 8);
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
                    <span className="cw-badge">{mode === 'effects' ? 'Effects on living cells' : 'Staying safe: time · distance · shielding'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'effects' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('effects')}>Effects on cells</button>
                    <button className={'cw-btn ' + (mode === 'protection' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('protection')}>Staying safe</button>
                </div>
                {mode === 'protection' && <>
                    <Slider label="Exposure time" min={5} max={120} step={5} value={time} onChange={setTime} suffix=" min" />
                    <Slider label="Distance from source" min={1} max={6} step={0.5} value={distance} onChange={setDistance} suffix=" m" />
                    <div className="cw-btnrow">
                        {SHIELDS.map((s, i) => (
                            <button key={s.key} className={'cw-btn ' + (shieldIdx === i ? 'cw-btn-save' : 'cw-btn-ghost')} style={{ fontSize: 11 }} onClick={() => setShieldIdx(i)}>{s.label}</button>
                        ))}
                    </div>
                </>}
                {mode === 'protection'
                    ? <Stat label="Relative dose received" value={dose.toFixed(0)} tone={dose > 60 ? 'warn' : 'acc'} sub={<>Dose is <b>lower</b> with <b>less time</b>, <b>more distance</b> and <b>more shielding</b>. Here: {time} min, {distance} m, {shield.desc}.</>} />
                    : <Stat label="Ionising radiation on cells" value="death · mutation · cancer" tone="warn" sub={<>Ionising radiation removes electrons from atoms inside cells, which can <b>kill</b> cells, cause <b>mutations</b> (changes to the DNA) or lead to <b>cancer</b>. The more the dose, the greater the risk.</>} />}
                <Flag kind="neutral">
                    Nuclear radiation is <b>ionising</b>: it can damage living cells, causing <b>cell death</b>, <b>mutations</b> and <b>cancer</b>.
                    To handle radioactive materials safely, keep the <b>dose</b> low by three means — <b>reduce the exposure time</b>,
                    <b> increase the distance</b> between the source and living tissue, and <b>use shielding</b> (lead or concrete) to
                    <b> absorb</b> the radiation. Sources are handled with tongs, kept in lead-lined containers, and stored well away from people.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, time, distance, shield: shieldIdx })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
