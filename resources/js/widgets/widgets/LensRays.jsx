import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: lens_rays ─────────────────────────────────────────────────────────
// Ray-box sends three rays through lenses at marked positions. The ray polyline is
// recreated from the exam figure (control points per position). Each MCQ Options
// row assigns a lens type (converging / diverging) to every position; on pick the
// widget draws the lens glyph at each position — convex arrows-out for converging,
// concave arrows-in for diverging — tinted green where it matches the true answer
// and red where it doesn't, and annotates the bend direction.
// config: {
//   positions:[f1,f2,f3],          // x fractions of the three lens positions
//   amps:[a0,a1,a2,a3,a4],         // top-ray height (px, +up) at box, pos1, pos2, pos3, end
//   optionsLead, hint,
//   options:[{label, lenses:['converging'|'diverging', x3], correct, note}]
// }

const VW = 1040, VH = 520;

function lensGlyph(ctx, x, yc, type, col) {
    const hgt = 120;
    ctx.strokeStyle = col; ctx.lineWidth = 3;
    ctx.beginPath(); ctx.moveTo(x, yc - hgt / 2); ctx.lineTo(x, yc + hgt / 2); ctx.stroke();
    ctx.fillStyle = col;
    const head = (yTip, dir) => { // dir +1 arrow points outward-up at top / outward-down at bottom
        ctx.beginPath(); ctx.moveTo(x, yTip); ctx.lineTo(x - 9, yTip - dir * 14); ctx.lineTo(x + 9, yTip - dir * 14); ctx.closePath(); ctx.fill();
    };
    if (type === 'converging') { head(yc - hgt / 2, 1); head(yc + hgt / 2, -1); } // arrows point OUT (convex)
    else { head(yc - hgt / 2 + 14, -1); head(yc + hgt / 2 - 14, 1); }              // arrows point IN (concave)
}

export default function LensRays({ config = {}, onReady, onAddToNote }) {
    const { positions = [0.3, 0.5, 0.68], amps = [0, 55, 95, 45, 70] } = config;
    const [picked, setPicked] = useState(null);
    const stRef = useRef(picked); stRef.current = picked;
    const cvRef = useRef(null);
    const truth = (config.options || []).find((o) => o.correct)?.lenses || [];

    useEffect(() => { onReady && onReady({ getState: () => ({ picked: picked?.label }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0, t0 = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const draw = (ts) => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            if (!t0) t0 = ts; const frame = (ts - t0) / 1000;
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399'), bad = '#fb7185';
            const p = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bgc = ctx.createLinearGradient(0, 0, 0, VH); bgc.addColorStop(0, '#0a1622'); bgc.addColorStop(1, '#0c1a12'); ctx.fillStyle = bgc; ctx.fillRect(0, 0, VW, VH);

            const axisY = VH / 2, boxX = 130;
            const xs = [boxX, ...positions.map((f) => f * VW), VW - 40];
            // ray-box
            ctx.strokeStyle = ink; ctx.lineWidth = 2.4; ctx.fillStyle = 'rgba(255,255,255,.05)';
            ctx.beginPath(); ctx.rect(50, axisY - 40, 76, 80); ctx.fill(); ctx.stroke();
            ctx.fillStyle = faint; ctx.font = '600 14px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText('ray-box', 88, axisY - 50);

            // three rays (top +amp, middle 0, bottom -amp) as polylines through the control points
            const drawRay = (sign) => {
                ctx.strokeStyle = ink; ctx.lineWidth = 2.2; ctx.beginPath();
                xs.forEach((x, i) => { const y = axisY - sign * amps[i]; ctx[i ? 'lineTo' : 'moveTo'](x, y); });
                ctx.stroke();
                // little travelling arrowheads between segments
                for (let i = 0; i < xs.length - 1; i++) {
                    const t = 0.5, x = xs[i] + (xs[i + 1] - xs[i]) * t, y = (axisY - sign * amps[i]) + ((axisY - sign * amps[i + 1]) - (axisY - sign * amps[i])) * t;
                    const a = Math.atan2((axisY - sign * amps[i + 1]) - (axisY - sign * amps[i]), xs[i + 1] - xs[i]);
                    ctx.fillStyle = ink; ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x - 12 * Math.cos(a - 0.4), y - 12 * Math.sin(a - 0.4)); ctx.lineTo(x - 12 * Math.cos(a + 0.4), y - 12 * Math.sin(a + 0.4)); ctx.closePath(); ctx.fill();
                }
            };
            drawRay(1); drawRay(-1);
            ctx.strokeStyle = 'rgba(166,196,179,.5)'; ctx.lineWidth = 1.4; ctx.setLineDash([2, 4]); ctx.beginPath(); ctx.moveTo(boxX, axisY); ctx.lineTo(VW - 40, axisY); ctx.stroke(); ctx.setLineDash([]);

            // position dashed lines + labels + (on pick) lens glyphs
            positions.forEach((f, i) => {
                const x = f * VW;
                ctx.strokeStyle = faint; ctx.lineWidth = 1.4; ctx.setLineDash([6, 5]); ctx.beginPath(); ctx.moveTo(x, 60); ctx.lineTo(x, VH - 60); ctx.stroke(); ctx.setLineDash([]);
                ctx.fillStyle = faint; ctx.font = '600 14px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(`position ${i + 1}`, x, VH - 34);
                if (p && p.lenses) {
                    const chosen = p.lenses[i], correctType = truth[i];
                    const col = chosen === correctType ? acc : bad;
                    lensGlyph(ctx, x, axisY, chosen, col);
                    ctx.fillStyle = col; ctx.font = '700 13px Inter, sans-serif'; ctx.fillText(chosen, x, 50);
                }
            });

            // verdict
            if (p) {
                const col = p.correct ? acc : bad;
                ctx.fillStyle = col; ctx.font = '700 15px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(p.correct ? '✓ matches the ray bending at every lens' : '✗ at least one lens does not match the rays', VW / 2, 30);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [positions, amps, truth]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '2 / 1' }}>
                    <span className="cw-badge">Lens ray diagram</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Which lens type is at each position?'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>A <strong>converging</strong> lens bends rays <em>toward</em> the axis; a <strong>diverging</strong> lens bends them <em>away</em>. Look at how the rays kink at each position.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
