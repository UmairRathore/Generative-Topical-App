import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: reflection_angle ──────────────────────────────────────────────────
// A ray striking a plane mirror. The figure gives the angle between the incident
// ray and the mirror SURFACE; the angle of incidence is measured from the NORMAL,
// so it is (90 − surface angle). Reflection = incidence. Each MCQ option is an
// (incidence, reflection) pair; on pick the widget draws the reflected ray at the
// claimed reflection angle and marks both arcs green/red against the true values.
// config: {
//   mirror:'vertical'|'horizontal', surfaceAngle,   // angle between incident ray and mirror
//   optionsLead, hint,
//   options:[{label, incidence, reflection, correct, note}]
// }

const VW = 900, VH = 560;
const D2R = Math.PI / 180;
const dirPt = (px, py, deg, r) => [px + r * Math.cos(deg * D2R), py - r * Math.sin(deg * D2R)];

export default function ReflectionAngle({ config = {}, onReady, onAddToNote }) {
    const { mirror = 'vertical', surfaceAngle = 40 } = config;
    const trueInc = 90 - surfaceAngle; // angle of incidence from the normal
    const [picked, setPicked] = useState(null);
    const stRef = useRef(picked); stRef.current = picked;
    const cvRef = useRef(null);

    useEffect(() => { onReady && onReady({ getState: () => ({ picked: picked?.label }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        // Geometry in compass degrees (0=right,90=up). Normal points AWAY from the mirror into
        // the ray region. Vertical mirror → normal left (180°); incident ray sits `trueInc`
        // above the normal, reflected ray `r` below it.
        const normalDeg = mirror === 'vertical' ? 180 : 90;
        const surfDeg = mirror === 'vertical' ? 90 : 0; // "up along the mirror" reference for the surface angle

        const arc = (px, py, fromDeg, toDeg, rad, col, label) => {
            ctx.strokeStyle = col; ctx.lineWidth = 3; ctx.beginPath();
            const steps = 26; for (let i = 0; i <= steps; i++) { const t = fromDeg + (toDeg - fromDeg) * i / steps; const [ax, ay] = dirPt(px, py, t, rad); ctx[i ? 'lineTo' : 'moveTo'](ax, ay); } ctx.stroke();
            if (label != null) { const [mx, my] = dirPt(px, py, (fromDeg + toDeg) / 2, rad + 26); ctx.fillStyle = col; ctx.font = '700 16px "JetBrains Mono", monospace'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(label, mx, my); ctx.textBaseline = 'alphabetic'; }
        };
        const rayLine = (px, py, deg, r, col, lw, toPivot, label) => {
            const [ex, ey] = dirPt(px, py, deg, r); ctx.strokeStyle = col; ctx.lineWidth = lw;
            ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(ex, ey); ctx.stroke();
            const hd = toPivot ? deg + 180 : deg; const [hx, hy] = dirPt(px, py, deg, r * 0.55); const a = hd * D2R;
            ctx.fillStyle = col; ctx.beginPath(); ctx.moveTo(hx, hy); ctx.lineTo(hx - 15 * Math.cos(a - 0.4), hy + 15 * Math.sin(a - 0.4)); ctx.lineTo(hx - 15 * Math.cos(a + 0.4), hy + 15 * Math.sin(a + 0.4)); ctx.closePath(); ctx.fill();
            if (label) { const [lx, ly] = dirPt(px, py, deg, r + 24); ctx.fillStyle = col; ctx.font = '600 14px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(label, lx, ly); }
        };

        const draw = () => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399'), bad = '#fb7185', warn = '#fbbf24';
            const p = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            const px = mirror === 'vertical' ? VW * 0.6 : VW / 2, py = mirror === 'vertical' ? VH / 2 : VH * 0.58, R = 190;
            // mirror line + hatching (on the far side of the normal)
            ctx.strokeStyle = '#9aa6b8'; ctx.lineWidth = 4;
            if (mirror === 'vertical') { ctx.beginPath(); ctx.moveTo(px, 50); ctx.lineTo(px, VH - 50); ctx.stroke(); for (let y = 70; y < VH - 50; y += 26) { ctx.lineWidth = 1.5; ctx.beginPath(); ctx.moveTo(px, y); ctx.lineTo(px + 16, y - 14); ctx.stroke(); ctx.lineWidth = 4; } }
            else { ctx.beginPath(); ctx.moveTo(60, py); ctx.lineTo(VW - 60, py); ctx.stroke(); for (let x = 80; x < VW - 60; x += 26) { ctx.lineWidth = 1.5; ctx.beginPath(); ctx.moveTo(x, py); ctx.lineTo(x - 14, py + 16); ctx.stroke(); ctx.lineWidth = 4; } }
            ctx.fillStyle = faint; ctx.font = '600 16px Inter, sans-serif'; ctx.textAlign = 'left'; ctx.fillText('mirror', mirror === 'vertical' ? px + 20 : VW - 130, mirror === 'vertical' ? 70 : py - 12);

            // normal (dashed)
            ctx.strokeStyle = faint; ctx.setLineDash([7, 5]); ctx.lineWidth = 1.8;
            const [nx, ny] = dirPt(px, py, normalDeg, R); ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(nx, ny); ctx.stroke(); ctx.setLineDash([]);
            ctx.fillStyle = faint; ctx.font = 'italic 600 14px Inter, sans-serif'; ctx.textAlign = 'center'; const [nlx, nly] = dirPt(px, py, normalDeg, R + 22); ctx.fillText('normal', nlx, nly);

            // incident ray (fixed) at trueInc above the normal
            const incDeg = normalDeg - trueInc;
            rayLine(px, py, incDeg, R, warn, 3, true, 'incident ray');
            // surface-angle annotation (between incident ray and mirror surface)
            arc(px, py, surfDeg, incDeg, 54, faint, `${surfaceAngle}°`);

            // on pick: claimed incidence arc + reflected ray at claimed reflection angle
            if (p) {
                const incOK = p.incidence === trueInc;
                arc(px, py, normalDeg, incDeg, 92, incOK ? acc : bad, `i = ${p.incidence}°`);
                const reflDeg = normalDeg + p.reflection;
                const reflOK = p.reflection === trueInc;
                rayLine(px, py, reflDeg, R, reflOK ? acc : bad, 3, false, 'reflected ray');
                arc(px, py, reflDeg, normalDeg, 122, reflOK ? acc : bad, `r = ${p.reflection}°`);
                ctx.fillStyle = p.correct ? acc : bad; ctx.font = '700 15px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(p.correct ? '✓ i and r both measured from the normal, and equal' : '✗ check: angles are from the normal, and i = r', VW / 2, 34);
            }
            // pivot
            ctx.fillStyle = ink; ctx.beginPath(); ctx.arc(px, py, 4, 0, 6.283); ctx.fill();
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [mirror, surfaceAngle, trueInc]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '9 / 6' }}>
                    <span className="cw-badge">Reflection</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Angle of incidence and reflection?'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>Angles are always measured from the <strong>normal</strong>, not the mirror. A ray {surfaceAngle}° from the mirror is {trueInc}° from the normal, and reflection equals incidence.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
