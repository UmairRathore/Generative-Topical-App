import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: star_lifecycle_lab ───────────────────────────────────────────────
// Bespoke 6.2.2 hero (immersive 2D). Galaxies and the life cycle of a star.
//   GALAXY: the Milky Way — billions of stars — with the Sun marked as one ordinary
//           star, and the light-year as the unit of astronomical distance.
//   LIFECYCLE: an interstellar cloud → protostar → stable (main-sequence) star →
//           (forks by mass) red giant → planetary nebula → white dwarf, OR
//           red supergiant → supernova → neutron star / black hole; the supernova
//           nebula may form new stars.
// config: { mode, stage, massive }

const STAGES = {
    low: [
        { t: 'Interstellar cloud', d: 'A cloud of gas and dust containing hydrogen.' },
        { t: 'Protostar', d: 'The cloud collapses under its own gravity and heats up.' },
        { t: 'Stable star', d: 'Fusion begins; the outward push balances gravity — a stable (main-sequence) star.' },
        { t: 'Runs out of hydrogen', d: 'Most of the core hydrogen has fused to helium; the star expands.' },
        { t: 'Red giant', d: 'A less massive star swells into a red giant.' },
        { t: 'Planetary nebula', d: 'The outer layers drift off as a planetary nebula…' },
        { t: 'White dwarf', d: '…leaving a hot, dense white dwarf at the centre.' },
    ],
    high: [
        { t: 'Interstellar cloud', d: 'A cloud of gas and dust containing hydrogen.' },
        { t: 'Protostar', d: 'The cloud collapses under its own gravity and heats up.' },
        { t: 'Stable star', d: 'Fusion begins; the outward push balances gravity — a stable (main-sequence) star.' },
        { t: 'Runs out of hydrogen', d: 'Most of the core hydrogen has fused to helium; the star expands.' },
        { t: 'Red supergiant', d: 'A more massive star swells into a red supergiant.' },
        { t: 'Supernova', d: 'It explodes as a supernova, forming a nebula of hydrogen and new heavier elements…' },
        { t: 'Neutron star / black hole', d: '…leaving a neutron star or a black hole. The nebula may form new stars.' },
    ],
};

export default function StarLifecycleLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['galaxy', 'lifecycle'].includes(config.mode) ? config.mode : 'lifecycle');
    const [stage, setStage] = useState(Number.isFinite(config.stage) ? config.stage : 2);
    const [massive, setMassive] = useState(!!config.massive);
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, stage, massive };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, stage: st.current.stage, massive: st.current.massive }),
            setState: (s) => { if (['galaxy', 'lifecycle'].includes(s?.mode)) setMode(s.mode); if (Number.isFinite(s?.stage)) setStage(s.stage); if (typeof s?.massive === 'boolean') setMassive(s.massive); },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf;
        const draw = (now) => {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight;
            if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const faint = cssVar('--ink-faint', '#68877A'), ink = cssVar('--ink-soft', '#A6C4B3');
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', cyan = '#38BDF8', rose = '#FB7185', violet = '#a78bfa';
            const S = st.current, t = now / 1000;
            ctx.fillStyle = '#05070d'; ctx.fillRect(0, 0, w, h);
            for (let i = 0; i < 80; i++) { const sx = (i * 137.5) % w, sy = (i * 47.3) % h; ctx.fillStyle = `rgba(255,255,255,${0.1 + 0.12 * Math.sin(i + t)})`; ctx.fillRect(sx, sy, 1.2, 1.2); }
            ctx.textAlign = 'center';

            if (S.mode === 'galaxy') {
                const cx = w * 0.36, cy = h * 0.46;
                // spiral galaxy
                ctx.save(); ctx.translate(cx, cy); ctx.rotate(t * 0.05);
                for (let arm = 0; arm < 3; arm++) { for (let i = 0; i < 120; i++) { const th = i * 0.09 + arm * 2.094; const rr = i * 0.9; ctx.fillStyle = `rgba(${180 + i % 60},${190},255,${0.5 - i / 300})`; ctx.beginPath(); ctx.arc(Math.cos(th) * rr, Math.sin(th) * rr * 0.6, 1.2, 0, 7); ctx.fill(); } }
                ctx.restore();
                const bg = ctx.createRadialGradient(cx, cy, 0, cx, cy, 30); bg.addColorStop(0, 'rgba(255,240,200,0.9)'); bg.addColorStop(1, 'rgba(255,240,200,0)'); ctx.fillStyle = bg; ctx.beginPath(); ctx.arc(cx, cy, 30, 0, 7); ctx.fill();
                // mark the Sun out on an arm
                const sxp = cx + 70, syp = cy + 20; ctx.fillStyle = amber; ctx.beginPath(); ctx.arc(sxp, syp, 3, 0, 7); ctx.fill();
                ctx.strokeStyle = amber; ctx.lineWidth = 1; ctx.beginPath(); ctx.arc(sxp, syp, 7 + Math.sin(t * 2) * 1.5, 0, 7); ctx.stroke();
                ctx.fillStyle = amber; ctx.font = '700 10px Inter'; ctx.fillText('the Sun (one star)', sxp + 4, syp - 12);
                ctx.fillStyle = ink; ctx.font = '700 12px Inter'; ctx.fillText('the Milky Way galaxy', cx, cy + h * 0.34);
                // facts
                ctx.textAlign = 'left';
                const facts = [['A galaxy =', 'many billions of stars'], ['Our galaxy', 'the Milky Way'], ['The Sun', 'one star in the Milky Way'], ['Other stars', 'far more distant than the Sun'], ['Distances in', 'light-years (distance light travels in 1 year)']];
                let fy = h * 0.2; facts.forEach(([a, b]) => { ctx.fillStyle = acc; ctx.font = '700 10px Inter'; ctx.fillText(a, w * 0.66, fy); ctx.fillStyle = ink; ctx.font = '600 10px Inter'; ctx.fillText(b, w * 0.66, fy + 13); fy += 32; });
            }

            else { // lifecycle
                const path = S.massive ? STAGES.high : STAGES.low; const i = Math.max(0, Math.min(6, S.stage));
                const cx = w * 0.32, cy = h * 0.42;
                const drawStar = (r, col, glow) => { const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, r * (glow || 1.8)); g.addColorStop(0, col); g.addColorStop(1, 'rgba(0,0,0,0)'); ctx.fillStyle = g; ctx.beginPath(); ctx.arc(cx, cy, r * (glow || 1.8), 0, 7); ctx.fill(); };
                const cloud = (spread) => { for (let k = 0; k < 40; k++) { const a = k * 0.618 * 6.283 + t * 0.1; const rr = spread * (0.3 + 0.7 * ((k * 17) % 10) / 10); ctx.fillStyle = `rgba(${120 + k % 60},${140},${180},0.4)`; ctx.beginPath(); ctx.arc(cx + Math.cos(a) * rr, cy + Math.sin(a) * rr * 0.7, 3, 0, 7); ctx.fill(); } };
                // draw the current stage
                if (i === 0) { cloud(60); }
                else if (i === 1) { cloud(38); drawStar(10, 'rgba(255,180,120,0.7)'); }
                else if (i === 2) { drawStar(16, 'rgba(255,241,180,0.95)'); ctx.fillStyle = amber; ctx.beginPath(); ctx.arc(cx, cy, 16, 0, 7); ctx.fill(); }
                else if (i === 3) { drawStar(24, 'rgba(255,200,120,0.85)'); ctx.fillStyle = '#f59e0b'; ctx.beginPath(); ctx.arc(cx, cy, 22, 0, 7); ctx.fill(); }
                else if (i === 4) { const R = S.massive ? 44 : 34; drawStar(R, 'rgba(248,113,113,0.6)'); ctx.fillStyle = '#ef4444'; ctx.beginPath(); ctx.arc(cx, cy, R, 0, 7); ctx.fill(); }
                else if (i === 5) { if (S.massive) { const fa = 0.5 + 0.5 * Math.sin(t * 4); drawStar(60, `rgba(255,241,180,${0.5 + 0.4 * fa})`, 1.2); for (let k = 0; k < 30; k++) { const a = k * 0.5, rr = 40 + (t * 40 + k * 8) % 60; ctx.fillStyle = `rgba(255,${150 + k % 80},80,${1 - rr / 100})`; ctx.beginPath(); ctx.arc(cx + Math.cos(a) * rr, cy + Math.sin(a) * rr, 2, 0, 7); ctx.fill(); } } else { for (let k = 0; k < 40; k++) { const a = k * 0.4, rr = 20 + (t * 15 + k * 6) % 55; ctx.strokeStyle = `rgba(167,139,250,${0.6 - rr / 100})`; ctx.fillStyle = `rgba(56,189,248,${0.5 - rr / 120})`; ctx.beginPath(); ctx.arc(cx + Math.cos(a) * rr, cy + Math.sin(a) * rr * 0.8, 3, 0, 7); ctx.fill(); } ctx.fillStyle = 'rgba(255,255,255,0.9)'; ctx.beginPath(); ctx.arc(cx, cy, 5, 0, 7); ctx.fill(); } }
                else { if (S.massive) { ctx.fillStyle = '#111'; ctx.beginPath(); ctx.arc(cx, cy, 14, 0, 7); ctx.fill(); ctx.strokeStyle = violet; ctx.lineWidth = 2; for (let ring = 1; ring <= 3; ring++) { ctx.globalAlpha = 0.5 / ring; ctx.beginPath(); ctx.arc(cx, cy, 14 + ring * 6, 0, 7); ctx.stroke(); } ctx.globalAlpha = 1; } else { drawStar(8, 'rgba(255,255,255,0.9)', 2.5); ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(cx, cy, 6, 0, 7); ctx.fill(); } }

                ctx.fillStyle = ink; ctx.font = '800 15px Inter'; ctx.textAlign = 'center'; ctx.fillText(`${i + 1}. ${path[i].t}`, cx, h * 0.8);
                // path label + progress dots
                ctx.fillStyle = S.massive ? rose : cyan; ctx.font = '600 10px Inter'; ctx.fillText(S.massive ? 'more massive star' : 'less massive star', cx, h * 0.88);
                for (let k = 0; k < 7; k++) { ctx.fillStyle = k === i ? acc : 'rgba(160,196,179,0.3)'; ctx.beginPath(); ctx.arc(cx - 60 + k * 20, h * 0.94, k === i ? 4 : 2.5, 0, 7); ctx.fill(); }
                // description panel
                ctx.textAlign = 'left'; ctx.fillStyle = ink; ctx.font = '600 11px Inter';
                const words = path[i].d.split(' '); let line = '', ly = h * 0.24; const maxw = w * 0.3;
                words.forEach(word => { const test = line + word + ' '; if (ctx.measureText(test).width > maxw) { ctx.fillText(line, w * 0.64, ly); line = word + ' '; ly += 16; } else line = test; });
                ctx.fillText(line, w * 0.64, ly);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const path = massive ? STAGES.high : STAGES.low; const cur = path[Math.max(0, Math.min(6, stage))];

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{mode === 'galaxy' ? 'Galaxies & the Milky Way' : 'The life cycle of a star'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'galaxy' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('galaxy')}>Galaxies</button>
                    <button className={'cw-btn ' + (mode === 'lifecycle' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('lifecycle')}>Life cycle</button>
                </div>
                {mode === 'lifecycle' && <>
                    <div className="cw-btnrow">
                        <button className={'cw-btn ' + (!massive ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMassive(false)}>Less massive</button>
                        <button className={'cw-btn ' + (massive ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMassive(true)}>More massive</button>
                    </div>
                    <Slider label={`Stage ${stage + 1} of 7`} min={0} max={6} step={1} value={stage} onChange={setStage} />
                    <Stat label={`${stage + 1}. ${cur.t}`} value={massive ? 'more massive path' : 'less massive path'} tone="acc" sub={cur.d} />
                </>}
                {mode === 'galaxy' && <Stat label="Galaxies & distances" value="Milky Way · light-years" tone="acc" sub={<>A <b>galaxy</b> is many <b>billions of stars</b>. The <b>Sun</b> is one star in the <b>Milky Way</b>; other stars are far more distant. Astronomical distances are measured in <b>light-years</b> — the distance light travels in one year.</>} />}
                <Flag kind="neutral">
                    A <b>galaxy</b> is made of many <b>billions of stars</b>; the Sun is a star in the <b>Milky Way</b>, and the other stars are much
                    <b> further</b> away. Distances are measured in <b>light-years</b> (the distance light travels in a year). A star's <b>life cycle</b>:
                    an <b>interstellar cloud</b> → <b>protostar</b> → <b>stable star</b> (gravity balanced by fusion) → when the hydrogen runs low it
                    swells to a <b>red giant</b> (less massive) or <b>red supergiant</b> (more massive) → then a <b>planetary nebula + white dwarf</b>,
                    or a <b>supernova</b> leaving a <b>neutron star or black hole</b>; the supernova's nebula may form <b>new stars</b>.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, stage, massive })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
