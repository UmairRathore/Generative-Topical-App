import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag } from '../primitives.jsx';

// ── Widget: applications_lab ─────────────────────────────────────────────────
// Bespoke Thermal Applications hero (5054 · 2.3.4). Four everyday scenes, each
// labelled with the transfer(s) it manages: a kitchen PAN (conducting base,
// insulating handle, convecting water), a ROOM heated by convection, an
// infrared THERMOMETER reading emitted radiation without contact, and a vacuum
// FLASK that defeats all three transfers at once. The synthesis of the whole
// 2.3 block. Illustrative scenario.
//
// Lake-bar immersion: rendered objects + labelled transfer arrows.
// config: { mode } · Notes contract: getState/setState carry {mode}.

const MODES = ['pan', 'room', 'thermo', 'flask'];

export default function ApplicationsLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(MODES.includes(config.mode) ? config.mode : 'pan');
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode }),
            setState: (s) => { if (MODES.includes(s?.mode)) setMode(s.mode); },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf;
        const draw = (now) => {
            const amb = now / 1000;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', cyan = '#38BDF8', rose = '#FB7185';
            const m = st.current.mode;
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#20262e'); bg.addColorStop(1, '#171c22');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);
            ctx.textAlign = 'center';
            const tag = (x, y, txt, col) => {
                ctx.font = '700 9.5px Inter, sans-serif';
                const tw = ctx.measureText(txt).width + 14;
                ctx.fillStyle = 'rgba(10,16,26,.6)'; ctx.beginPath(); ctx.roundRect(x - tw / 2, y - 9, tw, 18, 9); ctx.fill();
                ctx.fillStyle = col; ctx.fillText(txt, x, y + 3.5);
            };
            const rising = (x, y0, col) => {  // convection arrow
                for (let i = 0; i < 3; i++) {
                    const p = ((amb * 0.6 + i / 3) % 1);
                    ctx.fillStyle = `rgba(${col},${0.5 * (1 - p)})`;
                    ctx.beginPath(); ctx.arc(x, y0 - p * 40, 3, 0, Math.PI * 2); ctx.fill();
                }
            };

            if (m === 'pan') {
                const cx = w * 0.42, panY = h * 0.5;
                // hob glow
                const hg = ctx.createRadialGradient(cx, panY + 42, 4, cx, panY + 42, 60);
                hg.addColorStop(0, 'rgba(255,110,60,.8)'); hg.addColorStop(1, 'rgba(255,110,60,0)');
                ctx.fillStyle = hg; ctx.fillRect(cx - 70, panY + 20, 140, 40);
                // pan body (metal) + water
                ctx.fillStyle = 'rgba(56,160,210,.22)'; ctx.fillRect(cx - 52, panY - 26, 104, 30);
                rising(cx - 20, panY - 2, '120,200,255'); rising(cx + 20, panY - 2, '120,200,255');
                const metal = ctx.createLinearGradient(cx - 52, 0, cx + 52, 0);
                metal.addColorStop(0, '#9aa2ac'); metal.addColorStop(0.5, '#dfe4ea'); metal.addColorStop(1, '#9aa2ac');
                ctx.fillStyle = metal; ctx.fillRect(cx - 54, panY + 2, 108, 12);   // conducting base
                ctx.strokeStyle = '#6b7280'; ctx.lineWidth = 1; ctx.strokeRect(cx - 54, panY - 26, 108, 40);
                // conduction arrows up through base
                for (let i = -1; i <= 1; i++) { ctx.strokeStyle = rose; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(cx + i * 24, panY + 14); ctx.lineTo(cx + i * 24, panY + 2); ctx.stroke(); ctx.fillStyle = rose; ctx.beginPath(); ctx.moveTo(cx + i * 24, panY - 2); ctx.lineTo(cx + i * 24 - 3, panY + 4); ctx.lineTo(cx + i * 24 + 3, panY + 4); ctx.fill(); }
                // handle (insulator, dark)
                ctx.fillStyle = '#2b2320'; ctx.beginPath(); ctx.roundRect(cx + 54, panY - 22, 46, 12, 5); ctx.fill();
                tag(cx, panY + 42, 'metal base — CONDUCTS heat in', rose);
                tag(cx, panY - 40, 'water CONVECTS', cyan);
                tag(cx + 78, panY - 32, 'handle: INSULATOR', acc);
            } else if (m === 'room') {
                // room box
                ctx.strokeStyle = '#3d4652'; ctx.lineWidth = 2;
                ctx.strokeRect(w * 0.18, h * 0.16, w * 0.64, h * 0.66);
                // radiator on floor left
                const rx = w * 0.28, ry = h * 0.72;
                ctx.fillStyle = '#c94a3a'; ctx.beginPath(); ctx.roundRect(rx - 22, ry - 24, 44, 24, 3); ctx.fill();
                ctx.strokeStyle = '#8a2f24'; for (let i = -3; i <= 3; i++) { ctx.beginPath(); ctx.moveTo(rx + i * 6, ry - 24); ctx.lineTo(rx + i * 6, ry); ctx.stroke(); }
                // big convection loop of warm air
                ctx.setLineDash([4, 5]); ctx.strokeStyle = 'rgba(255,170,90,.5)'; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.ellipse(w * 0.5, h * 0.48, w * 0.26, h * 0.26, 0, 0, Math.PI * 2); ctx.stroke(); ctx.setLineDash([]);
                for (let i = 0; i < 6; i++) {
                    const a = amb * 0.5 + i / 6 * Math.PI * 2;
                    const x = w * 0.5 + Math.cos(a) * w * 0.26, y = h * 0.48 + Math.sin(a) * h * 0.26;
                    ctx.fillStyle = `rgba(255,170,90,.7)`; ctx.beginPath(); ctx.arc(x, y, 3, 0, Math.PI * 2); ctx.fill();
                }
                rising(rx, ry - 26, '255,170,90');
                tag(rx, ry + 12, 'heater / radiator', faint);
                tag(w * 0.5, h * 0.2, 'warm air rises, circulates — CONVECTION heats the room', amber);
            } else if (m === 'thermo') {
                // an object (cup) emitting IR toward a handheld IR thermometer
                const cupX = w * 0.32, cupY = h * 0.56;
                ctx.fillStyle = '#c96a4a'; ctx.beginPath(); ctx.roundRect(cupX - 22, cupY - 26, 44, 40, 4); ctx.fill();
                ctx.fillStyle = 'rgba(255,255,255,.1)'; ctx.fillRect(cupX - 18, cupY - 22, 8, 32);
                rising(cupX, cupY - 30, '220,230,240');
                // IR rays to the gun
                const gunX = w * 0.66, gunY = h * 0.5;
                for (let i = 0; i < 5; i++) {
                    const p = ((amb * 0.5 + i / 5) % 1);
                    const x = cupX + 24 + p * (gunX - cupX - 40);
                    ctx.strokeStyle = `rgba(255,150,80,${0.6 * Math.sin(p * Math.PI)})`; ctx.lineWidth = 2;
                    ctx.beginPath(); ctx.moveTo(x, cupY - 6 + (i - 2) * 6); ctx.lineTo(x + 12, cupY - 6 + (i - 2) * 6); ctx.stroke();
                }
                // IR thermometer gun
                ctx.fillStyle = '#3a3f47'; ctx.beginPath(); ctx.roundRect(gunX, gunY - 16, 40, 26, 4); ctx.fill();
                ctx.fillRect(gunX + 12, gunY + 10, 12, 20);
                ctx.fillStyle = '#0d130f'; ctx.beginPath(); ctx.roundRect(gunX + 6, gunY - 12, 28, 12, 2); ctx.fill();
                ctx.fillStyle = '#4ef0a8'; ctx.font = '700 9px "JetBrains Mono", monospace';
                ctx.fillText('62°C', gunX + 20, gunY - 3);
                tag(cupX, cupY + 26, 'object emits infrared', rose);
                tag(w * 0.5, h * 0.28, 'detects emitted RADIATION — no contact needed', amber);
            } else {
                // vacuum flask cutaway — defeats all three
                const fx = w * 0.42, fy0 = h * 0.16, fy1 = h * 0.86, fw = 96;
                // outer case
                ctx.fillStyle = '#3a3f47'; ctx.beginPath(); ctx.roundRect(fx - fw / 2 - 14, fy0, fw + 28, fy1 - fy0, 12); ctx.fill();
                // silvered double wall (two thin bright walls with vacuum gap between)
                const wallG = ctx.createLinearGradient(fx - fw / 2, 0, fx - fw / 2 + 10, 0);
                wallG.addColorStop(0, '#eef2f6'); wallG.addColorStop(1, '#9aa2ac');
                ctx.fillStyle = wallG;
                ctx.fillRect(fx - fw / 2, fy0 + 20, 6, fy1 - fy0 - 30); ctx.fillRect(fx - fw / 2 + 12, fy0 + 20, 6, fy1 - fy0 - 30);
                ctx.fillRect(fx + fw / 2 - 6, fy0 + 20, 6, fy1 - fy0 - 30); ctx.fillRect(fx + fw / 2 - 18, fy0 + 20, 6, fy1 - fy0 - 30);
                // vacuum gap label
                ctx.fillStyle = 'rgba(0,0,0,.5)'; ctx.fillRect(fx - fw / 2 + 6, fy0 + 20, 6, fy1 - fy0 - 30); ctx.fillRect(fx + fw / 2 - 12, fy0 + 20, 6, fy1 - fy0 - 30);
                // hot liquid inside
                const liq = ctx.createLinearGradient(0, fy0 + 40, 0, fy1 - 10);
                liq.addColorStop(0, 'rgba(201,106,74,.5)'); liq.addColorStop(1, 'rgba(150,60,40,.6)');
                ctx.fillStyle = liq; ctx.fillRect(fx - fw / 2 + 20, fy0 + 40, fw - 40, fy1 - fy0 - 52);
                rising(fx, fy0 + 44, '255,150,90');
                // stopper
                ctx.fillStyle = '#4a3b2a'; ctx.beginPath(); ctx.roundRect(fx - fw / 2 + 16, fy0 + 8, fw - 32, 26, 4); ctx.fill();
                // labels with leader lines
                const label = (tx, ty, px, py, txt, col) => {
                    ctx.strokeStyle = 'rgba(255,255,255,.25)'; ctx.lineWidth = 1;
                    ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(tx, ty); ctx.stroke();
                    ctx.fillStyle = col; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = tx > fx ? 'left' : 'right';
                    ctx.fillText(txt, tx + (tx > fx ? 4 : -4), ty + 3);
                };
                ctx.textAlign = 'center';
                label(fx + fw / 2 + 30, fy0 + 20, fx + fw / 2 - 3, fy0 + 30, 'stopper: stops', acc);
                ctx.textAlign = 'left'; ctx.fillStyle = acc; ctx.font = '600 9px Inter, sans-serif';
                ctx.fillText('convection + evaporation', fx + fw / 2 + 34, fy0 + 32);
                label(fx + fw / 2 + 30, fy0 + 90, fx + fw / 2 - 9, fy0 + 90, 'vacuum gap: stops', amber);
                ctx.fillStyle = amber; ctx.fillText('conduction + convection', fx + fw / 2 + 34, fy0 + 102);
                label(fx - fw / 2 - 30, fy0 + 140, fx - fw / 2 + 9, fy0 + 140, 'silvered walls:', cyan);
                ctx.textAlign = 'right'; ctx.fillStyle = cyan;
                ctx.fillText('reflect radiation', fx - fw / 2 - 34, fy0 + 152);
                ctx.textAlign = 'center'; ctx.fillStyle = ink; ctx.font = '700 10px Inter, sans-serif';
                ctx.fillText('the vacuum flask defeats all three transfers', w * 0.42, h - 8);
            }

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const info = {
        pan: { v: 'CONDUCTION + convection', s: <>the metal base <b>conducts</b> heat quickly into the food (and the water <b>convects</b>), while the handle is an <b>insulator</b> so it stays cool to hold</> },
        room: { v: 'CONVECTION', s: <>a heater warms the air, which becomes less dense and rises, circulating a <b>convection current</b> that carries heat around the whole room</> },
        thermo: { v: 'RADIATION', s: <>an infrared thermometer reads the <b>radiation an object emits</b> — hotter objects emit more — measuring temperature <b>without touching</b></> },
        flask: { v: 'ALL THREE blocked', s: <>the vacuum stops <b>conduction and convection</b>, the silvered walls <b>reflect radiation</b>, and the stopper blocks convection and evaporation — so a hot drink stays hot</> },
    }[mode];

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{{ pan: 'Kitchen pan', room: 'Heating a room', thermo: 'Infrared thermometer', flask: 'Vacuum flask' }[mode]}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="the transfer(s) at work" value={info.v} tone="acc" sub={info.s} />
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'pan' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('pan')}>🍳 Pan</button>
                    <button className={'cw-btn ' + (mode === 'room' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('room')}>🏠 Room</button>
                    <button className={'cw-btn ' + (mode === 'thermo' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('thermo')}>🌡 IR thermometer</button>
                    <button className={'cw-btn ' + (mode === 'flask' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('flask')}>🍵 Vacuum flask</button>
                </div>
                <Flag kind="neutral">
                    Every everyday heating or insulating job is one or more of the three transfers, managed on purpose. Good
                    conductors move heat where you want it (a pan base); insulators and vacuums block it (a handle, a flask);
                    shiny surfaces reflect radiation; convection stirs whole rooms. The <b>vacuum flask</b> is the masterpiece —
                    it shuts down all three at once.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode })}>📌 Save this application to my notes</button>
                )}
            </div>
        </div>
    );
}
