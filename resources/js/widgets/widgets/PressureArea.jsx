import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: pressure_area ─────────────────────────────────────────────────────
// Two identical blocks resting on soft ground in different orientations: the same
// weight (force) but different contact areas → different pressure (p = F/A). The
// smaller-footprint block sinks. The MCQ Options probe maps each "forces vs
// pressures — same/different" row to the physics. Reusable for solid-pressure Qs.
// config: {
//   force, unit ('N'), areaUnit ('m²'),
//   blocks:[{label, w, h, area}]  // two blocks; w/h are drawn footprint & height
//   optionsLead, hint,
//   options:[{label, forces:'same'|'different', pressures:'same'|'different', correct, note}]
// }

const VW = 1000, VH = 480;

export default function PressureArea({ config = {}, onReady, onAddToNote }) {
    const { force = 0, unit = 'N', areaUnit = 'm²', blocks = [] } = config;
    const [picked, setPicked] = useState(null);
    const stRef = useRef(picked); stRef.current = picked;
    const cvRef = useRef(null);

    useEffect(() => { onReady && onReady({ getState: () => ({ picked: picked?.label }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    // pressure per block (F / A); smaller area ⇒ bigger pressure ⇒ sinks
    const pres = blocks.map((b) => (b.area ? force / b.area : 0));
    const pmax = Math.max(...pres, 1);

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0, t0 = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const draw = (ts) => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            if (!t0) t0 = ts; const frame = (ts - t0) / 1000;
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399'), warn = '#fbbf24';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            const groundY = VH * 0.66;
            // soft earth
            ctx.fillStyle = 'rgba(120,90,60,.18)'; ctx.fillRect(0, groundY, VW, VH - groundY);
            ctx.strokeStyle = '#8a6a4a'; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(0, groundY); ctx.lineTo(VW, groundY); ctx.stroke();
            ctx.fillStyle = faint; ctx.font = '600 14px Inter, sans-serif'; ctx.textAlign = 'left'; ctx.fillText('soft earth', 20, groundY + 26);

            blocks.forEach((b, i) => {
                const cx = VW * (i === 0 ? 0.28 : 0.72);
                const sink = pres[i] === pmax ? (18 + Math.sin(frame * 1.6) * 4) : 0; // higher-pressure block sinks
                const w = b.w, h = b.h, topY = groundY - h + sink, baseY = groundY + sink;
                // block
                const isSel = false;
                ctx.fillStyle = 'rgba(180,190,205,.15)'; ctx.strokeStyle = ink; ctx.lineWidth = 2.6;
                ctx.beginPath(); ctx.rect(cx - w / 2, topY, w, h); ctx.fill(); ctx.stroke();
                // contact base highlight
                ctx.strokeStyle = warn; ctx.lineWidth = 5; ctx.beginPath(); ctx.moveTo(cx - w / 2, baseY); ctx.lineTo(cx + w / 2, baseY); ctx.stroke();
                // weight arrow (same on both)
                const a = topY - 80; ctx.strokeStyle = acc; ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(cx, a); ctx.lineTo(cx, topY - 8); ctx.stroke();
                ctx.fillStyle = acc; ctx.beginPath(); ctx.moveTo(cx, topY - 4); ctx.lineTo(cx - 8, topY - 18); ctx.lineTo(cx + 8, topY - 18); ctx.closePath(); ctx.fill();
                ctx.font = '600 15px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillStyle = acc; ctx.fillText(`weight ${force} ${unit}`, cx, a - 8);
                // labels
                ctx.fillStyle = ink; ctx.font = '700 15px Inter, sans-serif'; ctx.fillText(b.label, cx, topY + h / 2);
                ctx.fillStyle = warn; ctx.font = '600 13px "JetBrains Mono", monospace'; ctx.fillText(`area ${b.area} ${areaUnit}`, cx, baseY + 22);
                // pressure readout
                const pv = pres[i];
                ctx.fillStyle = pres[i] === pmax ? '#fb7185' : faint; ctx.font = '700 15px "JetBrains Mono", monospace';
                ctx.fillText(`p = ${force}/${b.area} = ${(+pv.toFixed(0))} ${unit}/${areaUnit}`, cx, groundY + 54);
            });

            // verdict banner when an option is picked
            const p = stRef.current;
            if (p) {
                const col = p.correct ? acc : '#fb7185';
                ctx.fillStyle = 'rgba(0,0,0,.35)'; ctx.fillRect(VW / 2 - 300, 24, 600, 40);
                ctx.strokeStyle = col; ctx.lineWidth = 2; ctx.strokeRect(VW / 2 - 300, 24, 600, 40);
                ctx.fillStyle = col; ctx.font = '700 16px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                ctx.fillText(`forces: ${p.forces}   ·   pressures: ${p.pressures}`, VW / 2, 44);
                ctx.textBaseline = 'alphabetic';
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [blocks, force, unit, areaUnit]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '25 / 12' }}>
                    <span className="cw-badge">Pressure = force ÷ area</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Compare the forces and pressures'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>Same weight on both, but the upright block presses on a <em>smaller area</em> — so a larger pressure, and it sinks.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
