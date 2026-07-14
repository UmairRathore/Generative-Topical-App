import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: prism_lab ────────────────────────────────────────────────────────
// Bespoke Dispersion showpiece (5054 · 3.2.4). Two modes:
//   PRISM    — a white beam enters a rendered translucent triangular glass
//              prism, refracts, and fans out of the far face into the visible
//              spectrum on a screen: violet deviated most, red least. Direction
//              rays + an angle-of-incidence control. Shows dispersion = white
//              light split into its colours because each is refracted differently.
//   SPECTRUM — the seven traditional colours (red→violet) as a glowing band,
//              with the frequency order (increasing toward violet) and the
//              wavelength order (increasing toward red) both marked.
//
// Lake-bar immersion + direction-ray convention.
// config: { mode, angle, order } · Notes: getState/setState carry the same.

const COLOURS = [
    { name: 'red', hex: '#FF4D4D' },
    { name: 'orange', hex: '#FF9F1C' },
    { name: 'yellow', hex: '#FFE14D' },
    { name: 'green', hex: '#48D96A' },
    { name: 'blue', hex: '#4D9DFF' },
    { name: 'indigo', hex: '#6C6CFF' },
    { name: 'violet', hex: '#B86CFF' },
];

export default function PrismLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['prism', 'spectrum'].includes(config.mode) ? config.mode : 'prism');
    const [angle, setAngle] = useState(typeof config.angle === 'number' ? config.angle : 40);
    const [order, setOrder] = useState(['frequency', 'wavelength'].includes(config.order) ? config.order : 'frequency');
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, angle, order };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, angle: st.current.angle, order: st.current.order }),
            setState: (s) => {
                if (['prism', 'spectrum'].includes(s?.mode)) setMode(s.mode);
                if (typeof s?.angle === 'number') setAngle(Math.max(20, Math.min(60, s.angle)));
                if (['frequency', 'wavelength'].includes(s?.order)) setOrder(s.order);
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
            const S = st.current;
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#0e131b'); bg.addColorStop(1, '#0a0e14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);

            const beam = (x0, y0, x1, y1, col, wide = 1) => {
                ctx.strokeStyle = col.replace(')', ',.16)').replace('rgb', 'rgba'); ctx.lineWidth = 8 * wide; ctx.lineCap = 'round';
                ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke();
                ctx.strokeStyle = col; ctx.lineWidth = 2.4 * wide;
                ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke();
            };
            const arrow = (x0, y0, x1, y1, col) => {
                const ang = Math.atan2(y1 - y0, x1 - x0), mx = x0 + (x1 - x0) * 0.5, my = y0 + (y1 - y0) * 0.5;
                ctx.fillStyle = col;
                ctx.beginPath(); ctx.moveTo(mx + Math.cos(ang) * 7, my + Math.sin(ang) * 7);
                ctx.lineTo(mx - Math.cos(ang - 0.5) * 8, my - Math.sin(ang - 0.5) * 8);
                ctx.lineTo(mx - Math.cos(ang + 0.5) * 8, my - Math.sin(ang + 0.5) * 8); ctx.closePath(); ctx.fill();
            };

            if (S.mode === 'prism') {
                // equilateral prism, apex up, centred
                const cx = w * 0.46, cy = h * 0.56, R = Math.min(w * 0.20, h * 0.36);
                const apex = { x: cx, y: cy - R };
                const bl = { x: cx - R * 0.87, y: cy + R * 0.5 };
                const br = { x: cx + R * 0.87, y: cy + R * 0.5 };
                // glass body with faint shimmer
                const gg = ctx.createLinearGradient(bl.x, apex.y, br.x, br.y);
                const sh = 0.10 + 0.03 * Math.sin(amb * 1.3);
                gg.addColorStop(0, `rgba(150,225,235,${0.10 + sh})`);
                gg.addColorStop(0.5, `rgba(190,240,248,${0.20 + sh})`);
                gg.addColorStop(1, `rgba(150,225,235,${0.10 + sh})`);
                ctx.fillStyle = gg;
                ctx.beginPath(); ctx.moveTo(apex.x, apex.y); ctx.lineTo(br.x, br.y); ctx.lineTo(bl.x, bl.y); ctx.closePath(); ctx.fill();
                ctx.strokeStyle = 'rgba(200,240,248,.65)'; ctx.lineWidth = 2; ctx.stroke();

                // incident white beam onto the left face
                const entry = { x: (apex.x + bl.x) / 2 + (bl.x - apex.x) * 0.06, y: (apex.y + bl.y) / 2 + (bl.y - apex.y) * 0.06 };
                const inLen = Math.min(w * 0.22, h * 0.4);
                const ia = (S.angle - 40) * Math.PI / 180; // tilt the incoming beam a little with the control
                const ix0 = entry.x - Math.cos(0.15 + ia) * inLen, iy0 = entry.y - Math.sin(0.15 + ia) * inLen;
                beam(ix0, iy0, entry.x, entry.y, 'rgb(255,255,255)', 1.15);
                arrow(ix0, iy0, entry.x, entry.y, '#ffffff');
                ctx.fillStyle = faint; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText('white light', ix0 + 4, iy0 - 6);

                // single refracted white beam across to the exit (right) face
                const exit = { x: (apex.x + br.x) / 2 - (br.x - apex.x) * 0.02, y: (apex.y + br.y) / 2 };
                beam(entry.x, entry.y, exit.x, exit.y, 'rgb(245,245,255)', 1.05);

                // dispersed fan out of the exit face → screen on the right
                const screenX = w * 0.93;
                const baseAng = -0.12 + (S.angle - 40) * 0.004;   // overall exit direction
                const spread = 0.34;                              // total fan angle (violet - red)
                COLOURS.forEach((c, i) => {
                    const t = i / (COLOURS.length - 1);           // 0 red … 1 violet
                    const a = baseAng + t * spread;               // violet deviates MORE (larger downward angle)
                    const ex = screenX;
                    const ey = exit.y + Math.tan(a) * (screenX - exit.x);
                    beam(exit.x, exit.y, ex, ey, `rgb(${parseInt(c.hex.slice(1, 3), 16)},${parseInt(c.hex.slice(3, 5), 16)},${parseInt(c.hex.slice(5, 7), 16)})`, 0.9);
                    if (i === 0 || i === COLOURS.length - 1) arrow(exit.x, exit.y, ex, ey, c.hex);
                });
                // glowing spectrum band on the screen
                const yTop = exit.y + Math.tan(baseAng) * (screenX - exit.x) - 4;
                const yBot = exit.y + Math.tan(baseAng + spread) * (screenX - exit.x) + 4;
                const strip = ctx.createLinearGradient(0, yTop, 0, yBot);
                COLOURS.forEach((c, i) => strip.addColorStop(i / (COLOURS.length - 1), c.hex));
                ctx.fillStyle = strip; ctx.fillRect(screenX, Math.min(yTop, yBot), 10, Math.abs(yBot - yTop));
                ctx.fillStyle = ink; ctx.font = '700 9px "JetBrains Mono", monospace'; ctx.textAlign = 'right';
                ctx.fillText('red (least bent)', screenX - 4, Math.min(yTop, yBot) + 6);
                ctx.fillText('violet (most bent)', screenX - 4, Math.max(yTop, yBot) - 2);
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('white light disperses — each colour refracts by a different amount', w * 0.5, h - 12);
            } else {
                // SPECTRUM mode — glowing red→violet band with frequency & wavelength orders
                const x0 = w * 0.08, x1 = w * 0.92, yb = h * 0.42, bh = Math.min(h * 0.26, 70);
                const grad = ctx.createLinearGradient(x0, 0, x1, 0);
                COLOURS.forEach((c, i) => grad.addColorStop(i / (COLOURS.length - 1), c.hex));
                ctx.fillStyle = grad;
                const r = 10; // rounded band
                ctx.beginPath();
                ctx.moveTo(x0 + r, yb); ctx.arcTo(x1, yb, x1, yb + bh, r); ctx.arcTo(x1, yb + bh, x0, yb + bh, r);
                ctx.arcTo(x0, yb + bh, x0, yb, r); ctx.arcTo(x0, yb, x1, yb, r); ctx.closePath(); ctx.fill();
                // colour labels
                ctx.font = '700 9px Inter, sans-serif'; ctx.textAlign = 'center';
                COLOURS.forEach((c, i) => {
                    const x = x0 + (x1 - x0) * (i + 0.5) / COLOURS.length;
                    ctx.fillStyle = (c.name === 'yellow' || c.name === 'orange') ? '#11161c' : '#0b0f14';
                    ctx.fillText(c.name.toUpperCase(), x, yb + bh / 2 + 3);
                });
                const emph = (isFreq) => (S.order === 'frequency') === isFreq;
                // frequency axis (top): increases toward violet (right)
                ctx.strokeStyle = emph(true) ? '#B86CFF' : 'rgba(255,255,255,.35)'; ctx.lineWidth = emph(true) ? 2.4 : 1.4;
                ctx.beginPath(); ctx.moveTo(x0, yb - 16); ctx.lineTo(x1, yb - 16); ctx.stroke();
                arrow(x1 - 30, yb - 16, x1, yb - 16, emph(true) ? '#B86CFF' : 'rgba(255,255,255,.6)');
                ctx.fillStyle = emph(true) ? '#D7B6FF' : faint; ctx.font = `${emph(true) ? '700' : '600'} 10px Inter, sans-serif`; ctx.textAlign = 'left';
                ctx.fillText('frequency increases  →  (violet highest)', x0 + 6, yb - 22);
                // wavelength axis (bottom): increases toward red (left)
                ctx.strokeStyle = emph(false) ? '#FF4D4D' : 'rgba(255,255,255,.35)'; ctx.lineWidth = emph(false) ? 2.4 : 1.4;
                ctx.beginPath(); ctx.moveTo(x1, yb + bh + 16); ctx.lineTo(x0, yb + bh + 16); ctx.stroke();
                arrow(x0 + 30, yb + bh + 16, x0, yb + bh + 16, emph(false) ? '#FF4D4D' : 'rgba(255,255,255,.6)');
                ctx.fillStyle = emph(false) ? '#FFB3B3' : faint; ctx.font = `${emph(false) ? '700' : '600'} 10px Inter, sans-serif`; ctx.textAlign = 'right';
                ctx.fillText('←  wavelength increases  (red longest)', x1 - 6, yb + bh + 30);
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('red · orange · yellow · green · blue · indigo · violet', w * 0.5, h - 12);
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
                    <span className="cw-badge">{mode === 'prism' ? 'Dispersion · white light through a prism' : 'The visible spectrum · frequency & wavelength'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'prism' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('prism')}>🔺 Prism</button>
                    <button className={'cw-btn ' + (mode === 'spectrum' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('spectrum')}>🌈 Spectrum</button>
                </div>

                {mode === 'prism' && (
                    <>
                        <Stat label="dispersion of white light" value="violet bends most" tone="acc"
                              sub={<>white light is a <b>mixture of colours</b>; the prism refracts each by a <b>different amount</b>, so they spread into a spectrum — <b>violet</b> is bent most, <b>red</b> least</>} />
                        <Slider label="angle of the incident beam" value={angle} min={20} max={60} step={1} onChange={setAngle} format={(x) => `${x}°`} />
                        <Flag kind="neutral">
                            The prism does not add colour. <b>White light already contains all the colours</b>; because each colour
                            travels at a slightly different speed in glass, each is <b>refracted by a different amount</b> — violet
                            (highest frequency) bends the most, red (lowest frequency) the least. Splitting white light into its
                            spectrum this way is called <b>dispersion</b>.
                        </Flag>
                    </>
                )}

                {mode === 'spectrum' && (
                    <>
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (order === 'frequency' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setOrder('frequency')}>Order by frequency</button>
                            <button className={'cw-btn ' + (order === 'wavelength' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setOrder('wavelength')}>Order by wavelength</button>
                        </div>
                        <Stat label={order === 'frequency' ? 'in order of increasing frequency' : 'in order of increasing wavelength'}
                              value={order === 'frequency' ? 'red → violet' : 'violet → red'} tone="acc"
                              sub={order === 'frequency'
                                  ? <>red has the <b>lowest</b> frequency, violet the <b>highest</b> — frequency rises red → orange → yellow → green → blue → indigo → violet</>
                                  : <>violet has the <b>shortest</b> wavelength, red the <b>longest</b> — wavelength rises violet → indigo → blue → green → yellow → orange → red</>} />
                        <Flag kind="neutral">
                            The traditional seven colours are <b>red, orange, yellow, green, blue, indigo, violet</b>. Going from red
                            to violet, <b>frequency increases</b> and <b>wavelength decreases</b>. So red is lowest-frequency /
                            longest-wavelength and violet is highest-frequency / shortest-wavelength — the two orders run opposite ways.
                        </Flag>
                    </>
                )}

                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, angle, order })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
