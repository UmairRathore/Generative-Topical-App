import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: charged_deflection ────────────────────────────────────────────────
// A charged particle enters a uniform (electric) field and follows a parabolic
// path. A reference particle's path is shown; the MCQ options are candidate paths
// for a second particle. Direction is set by the sign of the charge (opposite sign
// ⇒ opposite deflection); the amount is set by the charge-to-mass ratio (smaller
// q/m ⇒ less deflection). On pick the widget draws that path and judges it.
// config: {
//   fieldLabel, given:{label, dir:'up'|'down', mag},   // reference path (drawn dashed)
//   optionsLead, hint,
//   options:[{label, dir:'up'|'down'|'straight', mag, correct, note}]   // mag 0..1
// }

const VW = 900, VH = 520;

export default function ChargedDeflection({ config = {}, onReady, onAddToNote }) {
    const { fieldLabel = 'field', given = { label: 'given path', dir: 'down', mag: 1 } } = config;
    const [picked, setPicked] = useState(null);
    const stRef = useRef(picked); stRef.current = picked;
    const cvRef = useRef(null);

    useEffect(() => { onReady && onReady({ getState: () => ({ picked: picked?.label }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const xEntry = 150, xExit = VW - 90, yMid = VH / 2, maxDefl = 150;
        const pathY = (dir, mag, t) => { const val = mag * maxDefl * t * t; return dir === 'up' ? yMid - val : dir === 'down' ? yMid + val : yMid; };
        const drawPath = (dir, mag, col, lw, label, dash) => {
            ctx.strokeStyle = col; ctx.lineWidth = lw; ctx.setLineDash(dash || []); ctx.beginPath();
            let lx = xExit, ly = yMid;
            for (let i = 0; i <= 60; i++) { const t = i / 60, x = xEntry + (xExit - xEntry) * t, y = pathY(dir, mag, t); i ? ctx.lineTo(x, y) : ctx.moveTo(x, y); if (i === 60) { lx = x; ly = y; } }
            ctx.stroke(); ctx.setLineDash([]);
            // arrowhead at exit
            const t2 = 59 / 60, px = xEntry + (xExit - xEntry) * t2, py = pathY(dir, mag, t2); const a = Math.atan2(ly - py, lx - px);
            ctx.fillStyle = col; ctx.beginPath(); ctx.moveTo(lx, ly); ctx.lineTo(lx - 15 * Math.cos(a - 0.4), ly - 15 * Math.sin(a - 0.4)); ctx.lineTo(lx - 15 * Math.cos(a + 0.4), ly - 15 * Math.sin(a + 0.4)); ctx.closePath(); ctx.fill();
            if (label) { ctx.fillStyle = col; ctx.font = '600 14px Inter, sans-serif'; ctx.textAlign = 'left'; ctx.fillText(label, lx + 10, ly + 4); }
        };

        const draw = () => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399'), bad = '#fb7185', warn = '#fbbf24';
            const p = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            // field region
            ctx.fillStyle = 'rgba(120,150,180,.12)'; ctx.fillRect(xEntry - 40, 40, xExit - xEntry + 40, VH - 80);
            ctx.strokeStyle = 'rgba(120,150,180,.3)'; ctx.lineWidth = 1.5; ctx.strokeRect(xEntry - 40, 40, xExit - xEntry + 40, VH - 80);
            ctx.fillStyle = faint; ctx.font = '600 16px Inter, sans-serif'; ctx.textAlign = 'left'; ctx.fillText(fieldLabel, xEntry - 20, 68);

            // entering beam
            ctx.strokeStyle = ink; ctx.lineWidth = 2.4; ctx.beginPath(); ctx.moveTo(30, yMid); ctx.lineTo(xEntry, yMid); ctx.stroke();
            ctx.fillStyle = ink; ctx.beginPath(); ctx.arc(xEntry, yMid, 4, 0, 6.283); ctx.fill();

            // reference (given) path — dashed, always shown
            drawPath(given.dir, given.mag, warn, 2.4, given.label, [7, 5]);

            // picked candidate path
            if (p) { const col = p.correct ? acc : bad; drawPath(p.dir, p.mag, col, 3.2, `path ${p.label}`); ctx.fillStyle = col; ctx.font = '700 15px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(p.correct ? '✓ opposite direction, and deflected much less' : '✗ check the charge sign (direction) and the q/m (amount)', VW / 2, 30); }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [fieldLabel, given]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '9 / 5' }}>
                    <span className="cw-badge">Deflection in a field</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Which path does the particle follow?'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>The <strong>sign</strong> of the charge sets the <em>direction</em> of deflection; the <strong>charge-to-mass ratio</strong> (q/m) sets how <em>much</em> it deflects.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
