import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: wave_lab ─────────────────────────────────────────────────────────
// Bespoke General Waves hero (5054 · 3.1). Three modes: TRANSVERSE — a
// travelling rope wave with labelled wavelength/amplitude/crest/trough, a
// vibration-vs-energy contrast and a live v = fλ readout; LONGITUDINAL — a
// slinky carrying compressions and rarefactions with vibration parallel to
// travel; RIPPLE — a shimmering ripple tank showing reflection, refraction,
// and diffraction through a gap and at an edge. Scenario values illustrative.
//
// Lake-bar immersion: lit water surface with moving wavefronts, glowing crests.
// config: { mode, ripple } · Notes: getState/setState carry {mode, ripple, freq, wavelength}.

export default function WaveLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['transverse', 'longitudinal', 'ripple'].includes(config.mode) ? config.mode : 'transverse');
    const [ripple, setRipple] = useState(['reflect', 'refract', 'gap', 'edge'].includes(config.ripple) ? config.ripple : 'gap');
    const [freq, setFreq] = useState(2);        // Hz
    const [lambda, setLambda] = useState(2);    // m
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, ripple, freq, lambda };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, ripple: st.current.ripple, freq: st.current.freq, wavelength: st.current.lambda }),
            setState: (s) => {
                if (['transverse', 'longitudinal', 'ripple'].includes(s?.mode)) setMode(s.mode);
                if (['reflect', 'refract', 'gap', 'edge'].includes(s?.ripple)) setRipple(s.ripple);
                if (typeof s?.freq === 'number') setFreq(Math.max(0.5, Math.min(5, s.freq)));
                if (typeof s?.wavelength === 'number') setLambda(Math.max(1, Math.min(4, s.wavelength)));
            },
        });
    }, [onReady]); // eslint-disable-line

    const speed = freq * lambda;

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf;
        const draw = (nowMs) => {
            const t = nowMs / 1000;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
            const cyan = '#38BDF8', amber = '#FBBF24';
            const S = st.current;

            if (S.mode === 'transverse' || S.mode === 'longitudinal') {
                const bg = ctx.createLinearGradient(0, 0, 0, h);
                bg.addColorStop(0, '#1a2230'); bg.addColorStop(1, '#141a24');
                ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);
                const midY = h * 0.44, A = h * 0.16;
                const pxPerM = (w - 60) / 10;            // 10 m of wave across
                const k = (2 * Math.PI) / (S.lambda * pxPerM);
                const omega = 2 * Math.PI * S.freq;
                const phase = (x) => k * x - omega * t;

                if (S.mode === 'transverse') {
                    // energy-transfer arrow (rightwards)
                    ctx.strokeStyle = amber; ctx.fillStyle = amber; ctx.lineWidth = 2;
                    ctx.beginPath(); ctx.moveTo(w * 0.5 - 40, h - 24); ctx.lineTo(w * 0.5 + 40, h - 24); ctx.stroke();
                    ctx.beginPath(); ctx.moveTo(w * 0.5 + 46, h - 24); ctx.lineTo(w * 0.5 + 38, h - 28); ctx.lineTo(w * 0.5 + 38, h - 20); ctx.fill();
                    ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
                    ctx.fillText('energy travels →', w * 0.5, h - 10);
                    // the rope
                    ctx.strokeStyle = cyan; ctx.lineWidth = 3; ctx.beginPath();
                    for (let px = 30; px <= w - 30; px += 2) {
                        const y = midY - A * Math.sin(phase(px - 30));
                        px === 30 ? ctx.moveTo(px, y) : ctx.lineTo(px, y);
                    }
                    ctx.stroke();
                    // glow crest highlights
                    for (let px = 30; px <= w - 30; px += 3) {
                        const ph = phase(px - 30); if (Math.sin(ph) > 0.985) { ctx.fillStyle = 'rgba(120,220,255,.5)'; ctx.beginPath(); ctx.arc(px, midY - A, 4, 0, Math.PI * 2); ctx.fill(); }
                    }
                    // a single vibrating particle (up-down only)
                    const partX = 30 + 1.5 * S.lambda * pxPerM;
                    const partY = midY - A * Math.sin(phase(partX - 30));
                    ctx.fillStyle = amber; ctx.beginPath(); ctx.arc(partX, partY, 5, 0, Math.PI * 2); ctx.fill();
                    ctx.strokeStyle = 'rgba(251,191,36,.4)'; ctx.setLineDash([2, 3]);
                    ctx.beginPath(); ctx.moveTo(partX, midY - A); ctx.lineTo(partX, midY + A); ctx.stroke(); ctx.setLineDash([]);
                    ctx.fillStyle = amber; ctx.font = '600 8.5px Inter, sans-serif'; ctx.textAlign = 'left';
                    ctx.fillText('a point vibrates ↕ (at right angles)', partX + 8, partY - 8);
                    // wavelength bracket (crest to crest) + amplitude
                    const c1 = 30 + (0.25) * S.lambda * pxPerM, c2 = c1 + S.lambda * pxPerM;
                    ctx.strokeStyle = acc; ctx.lineWidth = 1.5;
                    ctx.beginPath(); ctx.moveTo(c1, midY - A - 14); ctx.lineTo(c2, midY - A - 14); ctx.stroke();
                    ctx.fillStyle = acc; ctx.textAlign = 'center'; ctx.font = '700 10px "JetBrains Mono", monospace';
                    ctx.fillText(`λ = ${S.lambda.toFixed(1)} m`, (c1 + c2) / 2, midY - A - 18);
                    ctx.strokeStyle = faint; ctx.beginPath(); ctx.moveTo(w - 40, midY); ctx.lineTo(w - 40, midY - A); ctx.stroke();
                    ctx.fillStyle = faint; ctx.textAlign = 'left'; ctx.font = '600 8.5px Inter, sans-serif';
                    ctx.fillText('amplitude', w - 36, midY - A / 2);
                    ctx.fillStyle = faint; ctx.fillText('crest', 34, midY - A - 2); ctx.fillText('trough', 34, midY + A + 10);
                    // mean line
                    ctx.strokeStyle = line; ctx.setLineDash([4, 4]); ctx.beginPath(); ctx.moveTo(30, midY); ctx.lineTo(w - 30, midY); ctx.stroke(); ctx.setLineDash([]);
                } else {
                    // longitudinal slinky: coil x-positions displaced along travel
                    const n = 70, x0 = 30, span = w - 60;
                    ctx.strokeStyle = 'rgba(160,200,175,.15)'; ctx.lineWidth = 1;
                    for (let i = 0; i < n; i++) {
                        const base = x0 + (span * i) / n;
                        const disp = 8 * Math.sin(phase(base - x0));
                        const x = base + disp;
                        // density → brightness (compression bright)
                        const nextDisp = 8 * Math.sin(phase((x0 + (span * (i + 1)) / n) - x0));
                        const gap = (span / n) + (nextDisp - disp);
                        const bright = Math.max(0.15, Math.min(1, (span / n) / Math.max(2, gap)));
                        ctx.strokeStyle = `rgba(120,200,255,${bright})`; ctx.lineWidth = 2;
                        ctx.beginPath(); ctx.moveTo(x, midY - A); ctx.lineTo(x, midY + A); ctx.stroke();
                    }
                    ctx.fillStyle = amber; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
                    // label a compression and a rarefaction
                    const compX = x0 + ((0.75 * S.lambda * pxPerM) % span);
                    ctx.fillStyle = acc; ctx.fillText('compression (bunched)', x0 + span * 0.3, midY - A - 10);
                    ctx.fillStyle = faint; ctx.fillText('rarefaction (spread out)', x0 + span * 0.7, midY - A - 10);
                    ctx.strokeStyle = amber; ctx.fillStyle = amber; ctx.lineWidth = 2;
                    ctx.beginPath(); ctx.moveTo(w * 0.5 - 40, midY + A + 26); ctx.lineTo(w * 0.5 + 40, midY + A + 26); ctx.stroke();
                    ctx.beginPath(); ctx.moveTo(w * 0.5 + 46, midY + A + 26); ctx.lineTo(w * 0.5 + 38, midY + A + 22); ctx.lineTo(w * 0.5 + 38, midY + A + 30); ctx.fill();
                    ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
                    ctx.fillText('vibration ↔ is PARALLEL to the energy travel →', w * 0.5, midY + A + 44);
                }
            } else {
                // ── RIPPLE TANK (top-down, shimmering water) ──
                const water = ctx.createLinearGradient(0, 0, 0, h);
                water.addColorStop(0, '#0e2233'); water.addColorStop(1, '#0a1a28');
                ctx.fillStyle = water; ctx.fillRect(0, 0, w, h);
                for (let i = 0; i < 5; i++) {
                    ctx.strokeStyle = 'rgba(120,190,230,.05)'; ctx.lineWidth = 8;
                    ctx.beginPath();
                    for (let x = 0; x <= w; x += 8) ctx.lineTo(x, h * (0.15 + i * 0.18) + Math.sin(x * 0.02 + t + i) * 6);
                    ctx.stroke();
                }
                const R = st.current.ripple;
                const wl = 28;
                const off = (t * 55) % wl;
                const setFront = (alpha = 0.8) => { ctx.strokeStyle = `rgba(150,220,255,${alpha})`; ctx.lineWidth = 2.4; };
                // a bold direction-of-travel ray with an arrowhead (a "ray")
                const ray = (x0, y0, ang, len, col) => {
                    const x1 = x0 + Math.cos(ang) * len, y1 = y0 + Math.sin(ang) * len;
                    ctx.strokeStyle = col; ctx.fillStyle = col; ctx.lineWidth = 2.6;
                    ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke();
                    ctx.beginPath(); ctx.moveTo(x1, y1);
                    ctx.lineTo(x1 - Math.cos(ang - 0.42) * 11, y1 - Math.sin(ang - 0.42) * 11);
                    ctx.lineTo(x1 - Math.cos(ang + 0.42) * 11, y1 - Math.sin(ang + 0.42) * 11);
                    ctx.closePath(); ctx.fill();
                };

                if (R === 'refract') {
                    // horizontal boundary: deep (fast) above, shallow (slow) below; ray bends toward normal
                    const by = h * 0.46, hitX = w * 0.46;
                    const th1 = 0.55, th2 = 0.31;                          // incident & refracted angle from the vertical normal
                    const d1 = { x: Math.sin(th1), y: Math.cos(th1) }, d2 = { x: Math.sin(th2), y: Math.cos(th2) };
                    const p1 = { x: Math.cos(th1), y: -Math.sin(th1) }, p2 = { x: Math.cos(th2), y: -Math.sin(th2) };
                    const wl2 = wl * 0.62;                                 // shorter wavelength in the shallow
                    ctx.fillStyle = 'rgba(10,42,62,.55)'; ctx.fillRect(0, by, w, h - by);
                    // deep wavefronts (perpendicular to the incident ray), clipped above the boundary
                    setFront(0.8);
                    ctx.save(); ctx.beginPath(); ctx.rect(0, 0, w, by); ctx.clip();
                    for (let k = -3; k < 15; k++) {
                        const s = k * wl - off; const cx = hitX - d1.x * s, cy = by - d1.y * s;
                        ctx.beginPath(); ctx.moveTo(cx - p1.x * 300, cy - p1.y * 300); ctx.lineTo(cx + p1.x * 300, cy + p1.y * 300); ctx.stroke();
                    }
                    ctx.restore();
                    // shallow wavefronts (shorter, perpendicular to the refracted ray), clipped below
                    ctx.save(); ctx.beginPath(); ctx.rect(0, by, w, h - by); ctx.clip();
                    for (let k = 0; k < 18; k++) {
                        const s = k * wl2 + off * (wl2 / wl); const cx = hitX + d2.x * s, cy = by + d2.y * s;
                        ctx.beginPath(); ctx.moveTo(cx - p2.x * 300, cy - p2.y * 300); ctx.lineTo(cx + p2.x * 300, cy + p2.y * 300); ctx.stroke();
                    }
                    ctx.restore();
                    // boundary + normal
                    ctx.strokeStyle = 'rgba(200,220,235,.45)'; ctx.lineWidth = 1.5; ctx.setLineDash([6, 5]);
                    ctx.beginPath(); ctx.moveTo(0, by); ctx.lineTo(w, by); ctx.stroke();
                    ctx.strokeStyle = 'rgba(255,255,255,.35)'; ctx.beginPath(); ctx.moveTo(hitX, by - 66); ctx.lineTo(hitX, by + 66); ctx.stroke(); ctx.setLineDash([]);
                    // DIRECTION-OF-TRAVEL RAY, bending toward the normal at the boundary
                    ray(hitX - d1.x * 155, by - d1.y * 155, Math.atan2(d1.y, d1.x), 155, '#FBBF24');
                    ray(hitX, by, Math.atan2(d2.y, d2.x), 150, '#FBBF24');
                    ctx.fillStyle = '#FBBF24'; ctx.font = '600 8.5px Inter, sans-serif'; ctx.textAlign = 'left';
                    ctx.fillText('direction of travel', hitX + 10, by + 78); ctx.fillText('bends toward the normal', hitX + 10, by + 90);
                    ctx.fillStyle = faint; ctx.textAlign = 'right';
                    ctx.fillText('deep — fast, long λ', w - 12, 18); ctx.fillText('shallow — slow, short λ', w - 12, h - 26);
                    ctx.fillStyle = '#FBBF24'; ctx.textAlign = 'left'; ctx.font = '600 9.5px Inter, sans-serif';
                    ctx.fillText('REFRACTION: waves slow, λ shortens, and the direction bends', 16, h - 10);
                } else if (R === 'reflect') {
                    // plane mirror; incident ray down-right, reflected up-right — angle in = angle out
                    const my = h * 0.74, hitX = w * 0.5, th = 0.62;
                    const di = { x: Math.sin(th), y: Math.cos(th) }, dr = { x: Math.sin(th), y: -Math.cos(th) };
                    const pi = { x: Math.cos(th), y: -Math.sin(th) }, pr = { x: Math.cos(th), y: Math.sin(th) };
                    ctx.strokeStyle = '#c9d2dc'; ctx.lineWidth = 5; ctx.beginPath(); ctx.moveTo(20, my); ctx.lineTo(w - 20, my); ctx.stroke();
                    ctx.save(); ctx.beginPath(); ctx.rect(0, 0, w, my); ctx.clip();
                    for (let k = -3; k < 15; k++) {
                        const s = k * wl - off;
                        setFront(0.8); let cx = hitX - di.x * s, cy = my - di.y * s;   // incident
                        ctx.beginPath(); ctx.moveTo(cx - pi.x * 280, cy - pi.y * 280); ctx.lineTo(cx + pi.x * 280, cy + pi.y * 280); ctx.stroke();
                        setFront(0.42); cx = hitX + dr.x * s; cy = my + dr.y * s;       // reflected
                        ctx.beginPath(); ctx.moveTo(cx - pr.x * 280, cy - pr.y * 280); ctx.lineTo(cx + pr.x * 280, cy + pr.y * 280); ctx.stroke();
                    }
                    ctx.restore();
                    ctx.strokeStyle = 'rgba(255,255,255,.35)'; ctx.setLineDash([5, 4]); ctx.lineWidth = 1;
                    ctx.beginPath(); ctx.moveTo(hitX, my - 96); ctx.lineTo(hitX, my + 12); ctx.stroke(); ctx.setLineDash([]);
                    ray(hitX - di.x * 150, my - di.y * 150, Math.atan2(di.y, di.x), 150, '#FBBF24');
                    ray(hitX, my, Math.atan2(dr.y, dr.x), 150, '#FBBF24');
                    ctx.fillStyle = '#FBBF24'; ctx.font = '600 8px "JetBrains Mono", monospace'; ctx.textAlign = 'left';
                    ctx.fillText('in', hitX - 46, my - 40); ctx.fillText('out', hitX + 34, my - 40);
                    ctx.font = '600 9.5px Inter, sans-serif';
                    ctx.fillText('REFLECTION: the direction bounces — angle in = angle out', 16, h - 10);
                } else {
                    // diffraction through a gap / at an edge, with direction arrows
                    const bx = w * 0.40, gapY = h * 0.5;
                    const gapH = R === 'gap' ? Math.max(wl, st.current.lambda * 12) : h;
                    ctx.strokeStyle = '#c9d2dc'; ctx.lineWidth = 5;
                    if (R === 'gap') { ctx.beginPath(); ctx.moveTo(bx, 8); ctx.lineTo(bx, gapY - gapH / 2); ctx.moveTo(bx, gapY + gapH / 2); ctx.lineTo(bx, h - 8); ctx.stroke(); }
                    else { ctx.beginPath(); ctx.moveTo(bx, 8); ctx.lineTo(bx, gapY); ctx.stroke(); }
                    // incident plane fronts + incident direction ray
                    setFront(0.8);
                    for (let i = -1; i < 10; i++) { const x = 10 + i * wl + off; if (x > bx - 4) continue; ctx.beginPath(); for (let y = 8; y <= h - 8; y += 6) ctx.lineTo(x, y); ctx.stroke(); }
                    ray(bx - 165, gapY, 0, 120, 'rgba(251,191,36,.9)');
                    // circular fronts spreading from the gap / round the edge
                    const cx = bx, cy = gapY;
                    const spr = R === 'gap' ? Math.min(1.25, 0.32 + (st.current.lambda * 12 / gapH) * 0.55) : 1.35;
                    for (let i = 0; i < 9; i++) {
                        const rad = ((t * 55 + i * wl) % (w * 0.62));
                        ctx.strokeStyle = `rgba(150,220,255,${0.6 * (1 - rad / (w * 0.62))})`; ctx.lineWidth = 2.2;
                        const a0 = R === 'gap' ? -spr : -1.4, a1 = R === 'gap' ? spr : 0.25;
                        ctx.beginPath(); ctx.arc(cx, cy, rad, a0, a1); ctx.stroke();
                    }
                    // fan of direction rays showing the new travel directions
                    (R === 'gap' ? [-spr * 0.8, 0, spr * 0.8] : [-1.0, -0.5, 0.05]).forEach((a) => ray(cx + 10, cy, a, 92, 'rgba(251,191,36,.85)'));
                    ctx.fillStyle = '#FBBF24'; ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'left';
                    ctx.fillText(R === 'gap'
                        ? 'DIFFRACTION (gap): the waves spread into new directions — most when gap ≈ λ'
                        : 'DIFFRACTION (edge): the direction bends round the barrier’s edge', 16, h - 10);
                }
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
                    <span className="cw-badge">{mode === 'transverse' ? 'Transverse · features + v = fλ' : mode === 'longitudinal' ? 'Longitudinal · compressions' : 'Ripple tank'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'transverse' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('transverse')}>〰 Transverse</button>
                    <button className={'cw-btn ' + (mode === 'longitudinal' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('longitudinal')}>⇄ Longitudinal</button>
                    <button className={'cw-btn ' + (mode === 'ripple' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('ripple')}>💧 Ripple tank</button>
                </div>
                {mode !== 'ripple' ? (
                    <>
                        <Stat label="wave speed  v = f λ" value={speed.toFixed(1)} unit="m/s" tone="acc"
                              sub={<>f = <b>{freq.toFixed(1)} Hz</b> × λ = <b>{lambda.toFixed(1)} m</b> — the wave carries <b>energy</b>, not matter; each point just vibrates</>} />
                        <Slider label="frequency" value={freq} min={0.5} max={5} step={0.5} onChange={setFreq} format={(x) => `${x.toFixed(1)} Hz`} />
                        <Slider label="wavelength" value={lambda} min={1} max={4} step={0.5} onChange={setLambda} format={(x) => `${x.toFixed(1)} m`} />
                        <Flag kind="neutral">
                            {mode === 'transverse'
                                ? <>The rope's energy travels <b>rightwards</b>, but each point only moves <b>up and down</b> — at right angles to the travel. That is a <b>transverse</b> wave (light, water surface, seismic S-waves). Change f or λ and watch <b>v = fλ</b> update.</>
                                : <>Here the coils vibrate <b>back and forth along</b> the direction the energy travels — a <b>longitudinal</b> wave (sound, seismic P-waves), made of <b>compressions</b> (bunched) and <b>rarefactions</b> (spread out).</>}
                        </Flag>
                    </>
                ) : (
                    <>
                        <Stat label={{ reflect: 'Reflection at a plane surface', refract: 'Refraction (change of speed)', gap: 'Diffraction through a gap', edge: 'Diffraction at an edge' }[ripple]}
                              value={{ reflect: 'angle in = angle out', refract: 'slows & bends', gap: 'spreads through', edge: 'bends round' }[ripple]} tone="acc"
                              sub={<>a ripple tank makes water waves visible so their behaviour can be studied — the same behaviour all waves share</>} />
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (ripple === 'reflect' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setRipple('reflect')}>Reflection</button>
                            <button className={'cw-btn ' + (ripple === 'refract' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setRipple('refract')}>Refraction</button>
                            <button className={'cw-btn ' + (ripple === 'gap' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setRipple('gap')}>Diffraction · gap</button>
                            <button className={'cw-btn ' + (ripple === 'edge' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setRipple('edge')}>Diffraction · edge</button>
                        </div>
                        {ripple === 'gap' && (
                            <Slider label="wavelength (vs gap size)" value={lambda} min={1} max={4} step={0.5} onChange={setLambda} format={(x) => `${x.toFixed(1)} m`} />
                        )}
                        <Flag kind="neutral">
                            {ripple === 'gap'
                                ? <>Waves spread out as they pass a gap. The spreading is greatest when the <b>gap is about the same size as the wavelength</b> — widen the wavelength and watch the fronts fan out more.</>
                                : ripple === 'edge'
                                    ? <>Waves bend around the <b>edge</b> of a barrier into the region behind it — longer wavelengths bend more.</>
                                    : ripple === 'refract'
                                        ? <>Crossing into <b>shallow</b> water the waves <b>slow down</b>; their wavelength shortens and, meeting the boundary at an angle, they <b>bend</b> (refraction).</>
                                        : <>Wavefronts <b>reflect</b> off the plane barrier — the angle they arrive at equals the angle they leave at.</>}
                        </Flag>
                    </>
                )}
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, ripple, freq, wavelength: lambda })}>📌 Save this wave to my notes</button>
                )}
            </div>
        </div>
    );
}
