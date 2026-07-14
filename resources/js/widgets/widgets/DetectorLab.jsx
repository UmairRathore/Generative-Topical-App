import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: detector_lab ─────────────────────────────────────────────────────
// Bespoke 5.2.1 hero (immersive 2D). Detecting radioactivity.
//   DETECT     — the detectors: a cloud chamber / spark counter (for alpha) and a
//                Geiger-Müller (GM) tube + counter (for beta and gamma), showing
//                which detects what.
//   COUNT      — a GM tube registering counts; the count rate (counts per second)
//                and the CORRECTED count rate = measured − background.
//   BACKGROUND — the sources that make up background radiation (radon, rocks &
//                buildings, food & drink, cosmic rays) as a labelled breakdown.
// config: { mode, source, background }

const BG_SOURCES = [
    { key: 'radon', label: 'radon gas (in the air)', pct: 50, col: '#fb7185' },
    { key: 'rocks', label: 'rocks & buildings', pct: 17, col: '#fbbf24' },
    { key: 'cosmic', label: 'cosmic rays (from space)', pct: 12, col: '#38bdf8' },
    { key: 'food', label: 'food & drink', pct: 12, col: '#34d399' },
    { key: 'medical', label: 'medical (X-rays etc.)', pct: 9, col: '#a78bfa' },
];

export default function DetectorLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['detect', 'count', 'background'].includes(config.mode) ? config.mode : 'detect');
    const [sourceRate, setSourceRate] = useState(typeof config.source === 'number' ? config.source : 45); // counts/s from the source
    const [bg, setBg] = useState(typeof config.background === 'number' ? config.background : 5); // background counts/s
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, sourceRate, bg };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, source: st.current.sourceRate, background: st.current.bg }),
            setState: (s) => {
                if (['detect', 'count', 'background'].includes(s?.mode)) setMode(s.mode);
                if (typeof s?.source === 'number') setSourceRate(Math.max(0, Math.min(100, s.source)));
                if (typeof s?.background === 'number') setBg(Math.max(0, Math.min(20, s.background)));
            },
        });
    }, [onReady]); // eslint-disable-line

    const measured = sourceRate + bg;     // GM tube reads source + background
    const corrected = measured - bg;      // corrected count rate

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, t0 = performance.now(), lastTick = 0, ticks = [];
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
            const bgGrad = ctx.createLinearGradient(0, 0, 0, h);
            bgGrad.addColorStop(0, '#12161d'); bgGrad.addColorStop(1, '#0b0e14');
            ctx.fillStyle = bgGrad; ctx.fillRect(0, 0, w, h);
            ctx.textAlign = 'center'; ctx.lineCap = 'round';

            const gmTube = (cx, cy, s, label, detects) => {
                ctx.strokeStyle = ink; ctx.lineWidth = 2; ctx.fillStyle = 'rgba(255,255,255,.04)';
                ctx.beginPath(); ctx.roundRect(cx - 34 * s, cy - 14 * s, 68 * s, 28 * s, 6 * s); ctx.fill(); ctx.stroke();
                ctx.beginPath(); ctx.arc(cx - 34 * s, cy, 12 * s, Math.PI * 0.5, Math.PI * 1.5); ctx.stroke(); // window end
                ctx.fillStyle = faint; ctx.font = `600 ${Math.round(9 * s)}px Inter`; ctx.fillText(label, cx, cy - 22 * s);
                if (detects) { ctx.fillStyle = acc; ctx.font = `600 ${Math.round(8 * s)}px Inter`; ctx.fillText(detects, cx, cy + 26 * s); }
            };

            if (S.mode === 'detect') {
                // cloud chamber / spark counter (alpha) on the left; GM tube (beta/gamma) on the right
                ctx.strokeStyle = amber; ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(w * 0.26, h * 0.4, 46, 0, Math.PI * 2); ctx.stroke();
                // alpha tracks in the cloud chamber
                for (let i = 0; i < 5; i++) { const a = i * 1.2 + amb * 0.3; ctx.strokeStyle = rose; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(w * 0.26, h * 0.4); ctx.lineTo(w * 0.26 + Math.cos(a) * 40, h * 0.4 + Math.sin(a) * 40); ctx.stroke(); }
                ctx.fillStyle = ink; ctx.font = '700 10px Inter'; ctx.fillText('cloud chamber / spark counter', w * 0.26, h * 0.4 + 66);
                ctx.fillStyle = rose; ctx.font = '600 9px Inter'; ctx.fillText('detects α (alpha) — thick short tracks', w * 0.26, h * 0.4 + 82);
                gmTube(w * 0.68, h * 0.4, 1.3, 'Geiger-Müller (GM) tube + counter', '');
                ctx.fillStyle = cyan; ctx.font = '600 9px Inter'; ctx.fillText('detects β (beta) and γ (gamma)', w * 0.68, h * 0.4 + 40);
                ctx.fillStyle = faint; ctx.font = '600 10px Inter';
                ctx.fillText('a cloud chamber/spark counter is used for alpha; a GM tube + counter for beta and gamma', w * 0.5, h - 12);
            } else if (S.mode === 'count') {
                // GM tube on the left facing a source; counter display on the right
                gmTube(w * 0.3, h * 0.36, 1.4, 'GM tube', '');
                // source
                ctx.fillStyle = amber; ctx.beginPath(); ctx.arc(w * 0.08, h * 0.36, 10, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.fillText('source', w * 0.08, h * 0.36 + 24);
                // radiation dots streaming to the tube
                for (let i = 0; i < 9; i++) { const u = ((i / 9 + amb * 0.6) % 1); const x = w * 0.08 + u * (w * 0.22 - 20); ctx.fillStyle = cyan; ctx.beginPath(); ctx.arc(x, h * 0.36 + (i % 3 - 1) * 6, 2.5, 0, Math.PI * 2); ctx.fill(); }
                // counter box
                ctx.strokeStyle = ink; ctx.lineWidth = 2; ctx.strokeRect(w * 0.56, h * 0.24, w * 0.32, h * 0.24);
                ctx.fillStyle = '#08120c'; ctx.fillRect(w * 0.58, h * 0.28, w * 0.28, h * 0.14);
                ctx.fillStyle = '#39ff9a'; ctx.font = '800 22px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(`${measured} counts/s`, w * 0.72, h * 0.37);
                ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.fillText('counter reading (measured count rate)', w * 0.72, h * 0.52);
                // corrected calc
                ctx.fillStyle = ink; ctx.font = '700 12px "JetBrains Mono", monospace';
                ctx.fillText(`corrected count rate = measured − background`, w * 0.5, h * 0.72);
                ctx.fillStyle = acc; ctx.font = '800 14px "JetBrains Mono", monospace';
                ctx.fillText(`= ${measured} − ${bg} = ${corrected} counts/s (from the source)`, w * 0.5, h * 0.72 + 24);
                ctx.fillStyle = faint; ctx.font = '600 10px Inter';
                ctx.fillText('count rate = counts per second (or per minute); always subtract the background', w * 0.5, h - 12);
            } else {
                // BACKGROUND: horizontal stacked bar of sources
                const bx = w * 0.08, by = h * 0.34, bw = w * 0.84, bh = 40;
                let x = bx;
                BG_SOURCES.forEach((s) => { const seg = bw * s.pct / 100; ctx.fillStyle = s.col; ctx.fillRect(x, by, seg, bh); ctx.fillStyle = '#0b0e14'; ctx.font = '700 9px Inter'; ctx.textAlign = 'center'; if (seg > 30) ctx.fillText(`${s.pct}%`, x + seg / 2, by + bh / 2 + 3); x += seg; });
                ctx.strokeStyle = 'rgba(255,255,255,.2)'; ctx.strokeRect(bx, by, bw, bh);
                // legend
                let ly = by + bh + 24;
                BG_SOURCES.forEach((s, i) => { const lx = bx + (i % 3) * (bw / 3); const row = Math.floor(i / 3); ctx.fillStyle = s.col; ctx.fillRect(lx, ly + row * 20 - 8, 10, 10); ctx.fillStyle = ink; ctx.font = '600 9px Inter'; ctx.textAlign = 'left'; ctx.fillText(s.label, lx + 14, ly + row * 20); });
                ctx.fillStyle = acc; ctx.font = '700 12px Inter'; ctx.textAlign = 'center';
                ctx.fillText('BACKGROUND RADIATION — always around us, from natural and artificial sources', w * 0.5, h * 0.2);
                ctx.fillStyle = faint; ctx.font = '600 10px Inter';
                ctx.fillText('the biggest source is radon gas; also rocks/buildings, cosmic rays, food & drink, medical', w * 0.5, h - 12);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const badge = { detect: 'Detecting α, β and γ', count: 'Count rate & correcting for background', background: 'Background radiation' }[mode];

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
                    <button className={'cw-btn ' + (mode === 'detect' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('detect')}>🔬 Detectors</button>
                    <button className={'cw-btn ' + (mode === 'count' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('count')}>🔢 Count rate</button>
                    <button className={'cw-btn ' + (mode === 'background' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('background')}>🌍 Background</button>
                </div>

                {mode === 'detect' && (
                    <>
                        <Stat label="detecting radiation" value="right detector for each type" tone="acc"
                              sub={<>a <b>cloud chamber</b> or <b>spark counter</b> detects <b>alpha</b>; a <b>Geiger-Müller (GM) tube</b> with a <b>counter</b> detects <b>beta</b> and <b>gamma</b>.</>} />
                        <Flag kind="neutral">
                            Different detectors suit different radiations. <b>Alpha particles</b> are detected with a <b>cloud chamber</b> (they leave
                            thick, short tracks) or a <b>spark counter</b>. <b>Beta particles</b> and <b>gamma radiation</b> are detected with a
                            <b> Geiger-Müller (GM) tube</b> connected to a <b>counter</b> (or ratemeter), which clicks/counts each time radiation enters it.
                        </Flag>
                    </>
                )}

                {mode === 'count' && (
                    <>
                        <Stat label="corrected count rate" value={`${corrected} counts/s`} tone="acc"
                              sub={<>the GM tube reads the <b>measured</b> count rate = {measured} counts/s (source + background). Subtract the <b>background</b> ({bg}): corrected = {measured} − {bg} = <b>{corrected} counts/s</b> from the source.</>} />
                        <Slider label="source count rate" value={sourceRate} min={0} max={100} step={1} onChange={setSourceRate} format={(x) => `${x} c/s`} />
                        <Slider label="background count rate" value={bg} min={0} max={20} step={1} onChange={setBg} format={(x) => `${x} c/s`} />
                        <Flag kind="neutral">
                            The <b>count rate</b> is the number of counts registered <b>per second</b> (or per minute). But a detector also picks up
                            the ever-present <b>background radiation</b>. To find the count rate due to the <b>source alone</b>, you measure the
                            background first (with no source) and <b>subtract</b> it: <b>corrected count rate = measured count rate − background count rate</b>.
                        </Flag>
                    </>
                )}

                {mode === 'background' && (
                    <>
                        <Stat label="background radiation" value="natural + artificial sources" tone="acc"
                              sub={<>a low level of radiation is <b>always present</b> around us. The main sources are <b>radon gas</b>, <b>rocks & buildings</b>, <b>cosmic rays</b>, <b>food & drink</b> and <b>medical</b> uses.</>} />
                        <Flag kind="neutral">
                            <b>Background radiation</b> is the low level of ionising radiation that is <b>always around us</b>, whether or not there is a
                            radioactive source nearby. The sources that make a significant contribution include <b>radon gas</b> in the air (the
                            largest), <b>rocks and buildings</b>, <b>food and drink</b>, and <b>cosmic rays</b> from space. Because it is always there,
                            you must <b>subtract</b> it from any measurement to find the count rate due to a source.
                        </Flag>
                    </>
                )}

                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, source: sourceRate, background: bg })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
