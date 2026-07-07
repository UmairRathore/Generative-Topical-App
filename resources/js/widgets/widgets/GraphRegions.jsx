import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: graph_regions ─────────────────────────────────────────────────────
// A fixed exam graph (piecewise curve) where the MCQ answer is a FEATURE of the
// graph — a labelled segment, a point, or an area. Optionally shades named regions
// permanently (e.g. areas X and Y). Clicking an option highlights its segment /
// point / area on the curve, green if correct else red, and explains it.
// Covers: "which part shows terminal velocity", "what does area X represent",
// "where is the limit of proportionality".
// config: {
//   xLabel,xUnit,xMax, yLabel,yUnit,yMax,
//   curve:{points:[[x,y]…]}, regions:[{area:[x1,x2],label,tag}],
//   optionsLead, hint,
//   options:[{label, seg:[x1,x2] | point:[x,y] | area:[x1,x2], correct, note}]
// }

const VW = 760, VH = 500;
function curveY(pts, x) {
    for (let i = 0; i < pts.length - 1; i++) { const [x1, y1] = pts[i], [x2, y2] = pts[i + 1]; if (x >= x1 && x <= x2) { const t = (x - x1) / ((x2 - x1) || 1); return y1 + t * (y2 - y1); } }
    return pts[pts.length - 1][1];
}

export default function GraphRegions({ config = {}, onReady, onAddToNote }) {
    const { xLabel = 'x', xUnit = '', xMax = 10, yLabel = 'y', yUnit = '', yMax = 10, curve = { points: [[0, 0]] }, regions = [] } = config;
    const pts = curve.points || [[0, 0]];
    const [picked, setPicked] = useState(null);
    const stRef = useRef(picked); stRef.current = picked;
    const cvRef = useRef(null);

    useEffect(() => { onReady && onReady({ getState: () => ({ picked: picked?.label }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const draw = () => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399'), bad = '#fb7185';
            const p = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            const pad = { l: 60, r: 26, t: 30, b: 46 }, gw = VW - pad.l - pad.r, gh = VH - pad.t - pad.b;
            const X = (x) => pad.l + (x / xMax) * gw, Y = (y) => pad.t + gh - (y / yMax) * gh;

            // grid + axes
            ctx.strokeStyle = line; ctx.lineWidth = 1;
            for (let i = 0; i <= 5; i++) { const gy = pad.t + gh * i / 5; ctx.beginPath(); ctx.moveTo(pad.l, gy); ctx.lineTo(pad.l + gw, gy); ctx.stroke(); const gx = pad.l + gw * i / 5; ctx.beginPath(); ctx.moveTo(gx, pad.t); ctx.lineTo(gx, pad.t + gh); ctx.stroke(); }
            ctx.strokeStyle = ink; ctx.lineWidth = 1.6; ctx.beginPath(); ctx.moveTo(pad.l, pad.t); ctx.lineTo(pad.l, pad.t + gh); ctx.lineTo(pad.l + gw, pad.t + gh); ctx.stroke();
            ctx.fillStyle = faint; ctx.font = '600 13px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText(`${xLabel}${xUnit ? ' / ' + xUnit : ''} →`, pad.l + gw / 2, VH - 12);
            ctx.save(); ctx.translate(16, pad.t + gh / 2); ctx.rotate(-Math.PI / 2); ctx.fillText(`${yLabel}${yUnit ? ' / ' + yUnit : ''} →`, 0, 0); ctx.restore();
            ctx.textAlign = 'right'; ctx.fillText('0', pad.l - 6, pad.t + gh + 4);

            // permanent shaded regions (areas under the curve)
            const shadeArea = (x1, x2, fill) => { ctx.fillStyle = fill; ctx.beginPath(); ctx.moveTo(X(x1), Y(0)); const N = 60; for (let i = 0; i <= N; i++) { const x = x1 + (x2 - x1) * i / N; ctx.lineTo(X(x), Y(curveY(pts, x))); } ctx.lineTo(X(x2), Y(0)); ctx.closePath(); ctx.fill(); };
            regions.forEach((rg) => {
                if (!rg.area) return; const [a, b] = rg.area; shadeArea(a, b, 'rgba(166,196,179,.14)');
                ctx.strokeStyle = 'rgba(166,196,179,.4)'; ctx.setLineDash([4, 4]); ctx.lineWidth = 1.2; ctx.beginPath(); ctx.moveTo(X(b), Y(0)); ctx.lineTo(X(b), Y(curveY(pts, b))); ctx.stroke(); ctx.setLineDash([]);
                const mx = (a + b) / 2; ctx.fillStyle = ink; ctx.font = '700 18px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(rg.label, X(mx), Y(curveY(pts, mx) * 0.42));
                if (rg.tag) { ctx.fillStyle = faint; ctx.font = '600 11px Inter, sans-serif'; ctx.fillText(rg.tag, X(mx), Y(0) - 8); }
            });

            // the curve
            ctx.strokeStyle = ink; ctx.lineWidth = 2.6; ctx.beginPath();
            pts.forEach(([x, y], i) => { const px = X(x), py = Y(y); i ? ctx.lineTo(px, py) : ctx.moveTo(px, py); }); ctx.stroke();

            // static feature labels for options that mark a segment/point (so A/B/C/D show on the graph)
            (config.options || []).forEach((o) => {
                if (o.seg) { const mx = (o.seg[0] + o.seg[1]) / 2, my = curveY(pts, mx); ctx.fillStyle = faint; ctx.font = '700 15px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(o.label, X(mx), Y(my) - 14); }
                else if (o.point) { ctx.fillStyle = ink; ctx.beginPath(); ctx.arc(X(o.point[0]), Y(o.point[1]), 4, 0, 6.283); ctx.fill(); ctx.fillStyle = faint; ctx.font = '700 15px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(o.label, X(o.point[0]) - 2, Y(o.point[1]) - 14); }
            });

            // highlight the picked option
            if (p) {
                const col = p.correct ? acc : bad;
                if (p.seg) { ctx.strokeStyle = col; ctx.lineWidth = 6; ctx.beginPath(); const [a, b] = p.seg; const N = 40; for (let i = 0; i <= N; i++) { const x = a + (b - a) * i / N; const px = X(x), py = Y(curveY(pts, x)); i ? ctx.lineTo(px, py) : ctx.moveTo(px, py); } ctx.stroke(); }
                else if (p.point) { ctx.strokeStyle = col; ctx.lineWidth = 3; ctx.beginPath(); ctx.arc(X(p.point[0]), Y(p.point[1]), 12, 0, 6.283); ctx.stroke(); ctx.fillStyle = col; ctx.beginPath(); ctx.arc(X(p.point[0]), Y(p.point[1]), 4, 0, 6.283); ctx.fill(); }
                else if (p.area) { shadeArea(p.area[0], p.area[1], p.correct ? 'rgba(52,211,153,.28)' : 'rgba(251,113,133,.28)'); }
                ctx.fillStyle = col; ctx.font = '700 15px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(p.correct ? '✓ correct' : '✗ not this one', pad.l + gw / 2, pad.t - 10);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [xLabel, xUnit, xMax, yLabel, yUnit, yMax, curve, regions]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '19 / 12' }}>
                    <span className="cw-badge">Read the graph</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Pick the correct answer'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>Click an answer to highlight the part of the graph it refers to.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this graph to my notes</button>)}
            </div>
        </div>
    );
}
