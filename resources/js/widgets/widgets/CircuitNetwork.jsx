import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: circuit_network ───────────────────────────────────────────────────
// Config-driven resistor-network schematic. Lays out a main line left→right from
// a list of segments; each segment is either a single series resistor or a
// parallel block of branches. Animated current dots flow along the wires, a
// "combine" stepper reduces parallel→series→total, and the MCQ Options probe maps
// each answer to a bit of reasoning. Covers the recurring "total resistance" type.
// config: {
//   segments:[ {type:'series', r} | {type:'parallel', branches:[r,r,...]} ],
//   unit, optionsLead, hint,
//   options:[{label, value, correct, note}]
// }

const VW = 1000, VH = 420;
const parallelOf = (rs) => 1 / rs.reduce((s, r) => s + 1 / r, 0);

function segEquiv(seg) {
    return seg.type === 'parallel' ? parallelOf(seg.branches) : seg.r;
}

export default function CircuitNetwork({ config = {}, onReady, onAddToNote }) {
    const { segments = [], unit = 'Ω', emf = null, meter = null } = config;
    const [picked, setPicked] = useState(null);
    const [step, setStep] = useState(0); // 0 full · 1 parallels combined · 2 solved
    const stRef = useRef({ picked, step }); stRef.current = { picked, step };
    const cvRef = useRef(null);

    const total = segments.reduce((s, seg) => s + segEquiv(seg), 0);
    const hasParallel = segments.some((s) => s.type === 'parallel');
    // meter mode: an e.m.f. + a voltmeter/ammeter across one segment → current & reading
    const meterMode = emf != null && meter != null;
    const current = emf != null && total ? emf / total : null;
    const meterSeg = meter ? segments[meter.across] : null;
    const meterReading = meterMode
        ? (meter.type === 'ammeter' ? current : current * segEquiv(meterSeg))
        : null;
    const meterUnit = meter?.type === 'ammeter' ? 'A' : 'V';

    useEffect(() => { onReady && onReady({ getState: () => ({ step }), setState: (s) => setStep(s?.step ?? 0) }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0, t0 = 0, frame = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        // resistor box drawn along a horizontal wire centred at (cx,cy)
        const resistor = (cx, cy, label, col, live) => {
            const w = 116, h = 40;
            ctx.fillStyle = live ? 'rgba(52,211,153,.10)' : 'rgba(255,255,255,.04)';
            ctx.strokeStyle = col; ctx.lineWidth = 2.4;
            ctx.beginPath(); ctx.rect(cx - w / 2, cy - h / 2, w, h); ctx.fill(); ctx.stroke();
            ctx.fillStyle = col; ctx.font = '600 19px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillText(label, cx, cy);
        };
        const wire = (x1, y1, x2, y2, col) => { ctx.strokeStyle = col; ctx.lineWidth = 2.4; ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke(); };
        const node = (x, y, col) => { ctx.fillStyle = col; ctx.beginPath(); ctx.arc(x, y, 5, 0, 6.283); ctx.fill(); };

        const draw = (ts) => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            if (!t0) t0 = ts; frame = (ts - t0) / 1000;
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399');
            const { step: stp } = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            const baseY = VH * 0.5, x0 = 70, x1 = VW - 70;
            // battery terminals
            ctx.fillStyle = faint; ctx.font = '600 15px Inter, sans-serif'; ctx.textBaseline = 'alphabetic'; ctx.textAlign = 'center';
            node(x0, baseY, ink); node(x1, baseY, ink);
            ctx.fillText('+', x0, baseY - 16); ctx.fillText('−', x1, baseY - 16);

            ctx.fillStyle = faint; ctx.textAlign = 'center'; if (emf != null) ctx.fillText(`${emf} ${'V'}`, (x0 + x1) / 2, baseY - 16);

            // effective segment list for the current reduction step. In meter mode we never
            // collapse to a single box — the metered segment must stay visible.
            let segs;
            if (stp >= 2 && !meterMode) segs = [{ type: 'series', r: total, _total: true }];
            else if (stp >= 1) segs = segments.map((s) => s.type === 'parallel' ? { type: 'series', r: segEquiv(s), _reduced: true } : s);
            else segs = segments;

            const n = segs.length, gap = (x1 - x0) / n;
            const wirePts = []; // x positions where current dots travel along the main line
            let cursor = x0, meterX = null;
            wirePts.push(x0);
            segs.forEach((seg, i) => {
                if (meter && i === meter.across) meterX = x0 + (i + 0.5) * gap;
                const slotStart = x0 + i * gap, slotEnd = x0 + (i + 1) * gap, cx = (slotStart + slotEnd) / 2;
                if (seg.type === 'parallel') {
                    const brs = seg.branches, m = brs.length;
                    const nodeL = slotStart + gap * 0.16, nodeR = slotEnd - gap * 0.16;
                    wire(cursor, baseY, nodeL, baseY, ink); node(nodeL, baseY, ink); node(nodeR, baseY, ink);
                    const spread = 70;
                    brs.forEach((r, bi) => {
                        const by = baseY + (bi - (m - 1) / 2) * spread;
                        wire(nodeL, baseY, nodeL, by, faint); wire(nodeL, by, cx - 58, by, faint);
                        resistor(cx, by, `${r} ${unit}`, ink, true);
                        wire(cx + 58, by, nodeR, by, faint); wire(nodeR, by, nodeR, baseY, faint);
                    });
                    cursor = nodeR;
                } else {
                    const col = seg._total ? acc : ink;
                    wire(cursor, baseY, cx - 58, baseY, ink);
                    const lbl = seg._total ? `${(+total.toFixed(2))} ${unit}` : (seg._reduced ? `${(+seg.r.toFixed(2))} ${unit}` : `${seg.r} ${unit}`);
                    resistor(cx, baseY, lbl, seg._reduced || seg._total ? acc : col, true);
                    wire(cx + 58, baseY, slotEnd, baseY, ink);
                    cursor = slotEnd;
                }
                wirePts.push(slotEnd);
            });
            wire(cursor, baseY, x1, baseY, ink);

            // voltmeter / ammeter across the metered segment
            if (meterMode && meterX != null) {
                const my = baseY - 92, mr = 26;
                ctx.strokeStyle = faint; ctx.lineWidth = 1.6; ctx.setLineDash([5, 4]);
                ctx.beginPath(); ctx.moveTo(meterX - 58, baseY); ctx.lineTo(meterX - 58, my); ctx.lineTo(meterX - mr, my); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(meterX + 58, baseY); ctx.lineTo(meterX + 58, my); ctx.lineTo(meterX + mr, my); ctx.stroke(); ctx.setLineDash([]);
                ctx.strokeStyle = acc; ctx.lineWidth = 2.2; ctx.beginPath(); ctx.arc(meterX, my, mr, 0, 6.283); ctx.stroke();
                ctx.fillStyle = acc; ctx.font = '700 20px Georgia, serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                ctx.fillText(meter.type === 'ammeter' ? 'A' : 'V', meterX, my);
                if (stp >= 2) { ctx.font = '700 15px "JetBrains Mono", monospace'; ctx.fillText(`${+meterReading.toFixed(2)} ${meterUnit}`, meterX, my - 40); }
                ctx.textBaseline = 'alphabetic';
            }

            // animated current dots along the main line (skip the branch interiors — indicative flow)
            const speed = 130, span = x1 - x0;
            for (let k = 0; k < 7; k++) {
                const px = x0 + ((frame * speed + k * span / 7) % span);
                ctx.fillStyle = acc; ctx.beginPath(); ctx.arc(px, baseY, 3.4, 0, 6.283); ctx.globalAlpha = 0.85; ctx.fill(); ctx.globalAlpha = 1;
            }

            // step caption
            ctx.fillStyle = faint; ctx.font = '600 15px Inter, sans-serif'; ctx.textAlign = 'left'; ctx.textBaseline = 'alphabetic';
            const cap = stp === 0 ? 'Full circuit'
                : stp === 1 ? 'Parallel block combined'
                : meterMode ? `Current = ${emf} / ${(+total.toFixed(2))} = ${(+current.toFixed(3))} A` : `Single equivalent resistor = ${(+total.toFixed(2))} ${unit}`;
            ctx.fillText(cap, x0, VH - 22);
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [segments, unit, total, emf, meter, meterMode, current, meterReading]);

    // reduction working lines revealed by the stepper
    const parallelLines = segments.filter((s) => s.type === 'parallel').map((s) => {
        const inv = s.branches.map((r) => `1/${r}`).join(' + ');
        return `${s.branches.length} × parallel:  1/R = ${inv}  →  R = ${(+parallelOf(s.branches).toFixed(2))} ${unit}`;
    });
    const seriesParts = segments.map(segEquiv).map((r) => +r.toFixed(2));

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '20 / 9' }}>
                    <span className="cw-badge">Resistor network</span>
                    <canvas ref={cvRef} />
                </div>
                <div className="cw-readout">
                    <div className="cw-stat"><span className="cw-stat-label">Total resistance</span><span className="cw-stat-val">{step >= 2 ? `${+total.toFixed(2)} ${unit}` : '— ' + unit}</span></div>
                    {meterMode && <div className="cw-stat"><span className="cw-stat-label">Current</span><span className="cw-stat-val">{step >= 2 ? `${+current.toFixed(3)} A` : '—'}</span></div>}
                    {meterMode && <div className="cw-stat"><span className="cw-stat-label">{meter.type === 'ammeter' ? 'Ammeter' : 'Voltmeter'}</span><span className="cw-stat-val">{step >= 2 ? `${+meterReading.toFixed(2)} ${meterUnit}` : '—'}</span></div>}
                    <div className="cw-btn-row">
                        {hasParallel && <button className="cw-btn" disabled={step >= 1} onClick={() => setStep(1)}>① Combine parallel</button>}
                        <button className="cw-btn" disabled={step >= 2} onClick={() => setStep(2)}>{meterMode ? '② Solve current & reading' : '② Add in series'}</button>
                        <button className="cw-btn cw-btn-ghost" onClick={() => setStep(0)}>Reset</button>
                    </div>
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'What is the total resistance?'} onPick={(o) => { setPicked(o); if (o.correct) setStep(2); }} />
                )}
                {step >= 1 && parallelLines.map((l, i) => <Flag key={i} kind="neutral">{l}</Flag>)}
                {step >= 2 && <Flag kind="ok">Total: {seriesParts.join(' + ')} = <strong>{+total.toFixed(2)} {unit}</strong></Flag>}
                {step >= 2 && meterMode && <Flag kind="ok">Current = {emf} ÷ {+total.toFixed(2)} = <strong>{+current.toFixed(3)} A</strong>{meter.type !== 'ammeter' && <>, so the voltmeter reads {emf} ÷ {+total.toFixed(2)} × {+segEquiv(meterSeg).toFixed(2)} = <strong>{+meterReading.toFixed(2)} V</strong></>}</Flag>}
                <Flag kind="neutral">{config.hint || <>Combine the two parallel resistors first, then add the series resistor.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this circuit to my notes</button>)}
            </div>
        </div>
    );
}
