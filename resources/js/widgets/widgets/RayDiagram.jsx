import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: ray_diagram ──────────────────────────────────────────────────────
// Annotated optics diagram + option probe. Draws a surface (mirror / water) with
// a normal and a set of rays meeting at a pivot; each MCQ option highlights either
// an ANGLE (arc between two directions) or a candidate RAY, and explains it.
// Angles are compass degrees: 0°=right, 90°=up, 180°=left, 270°=down.
// config: { surface, rays:[{angle,label,toPivot,dashed}], options:[{label, arc:[a,b] | ray:{angle,dashed}, correct, note}] }

const VW = 1000, VH = 560;

function dirPt(px, py, deg, r) { const a = deg * Math.PI / 180; return [px + r * Math.cos(a), py - r * Math.sin(a)]; }
function head(ctx, x, y, deg, col) { const a = deg * Math.PI / 180, h = 15; ctx.fillStyle = col; ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x - h * Math.cos(a - 0.4), y + h * Math.sin(a - 0.4)); ctx.lineTo(x - h * Math.cos(a + 0.4), y + h * Math.sin(a + 0.4)); ctx.closePath(); ctx.fill(); }

export default function RayDiagram({ config = {}, onReady, onAddToNote }) {
    const [picked, setPicked] = useState(null);
    const stRef = useRef(picked); stRef.current = picked;
    const cvRef = useRef(null);
    const { surface = 'mirror', rays = [] } = config;

    useEffect(() => { onReady && onReady({ getState: () => ({ ...config }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const draw = () => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399');
            const px = VW / 2, py = surface === 'mirror' ? VH * 0.66 : VH * 0.42, R = 200;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            // surface
            if (surface === 'mirror') {
                ctx.strokeStyle = '#9aa6b8'; ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(80, py); ctx.lineTo(VW - 80, py); ctx.stroke();
                ctx.lineWidth = 1.5; for (let hx = 90; hx < VW - 80; hx += 22) { ctx.beginPath(); ctx.moveTo(hx, py); ctx.lineTo(hx - 12, py + 14); ctx.stroke(); }
                ctx.fillStyle = faint; ctx.font = '600 16px Inter, sans-serif'; ctx.textAlign = 'left'; ctx.fillText('mirror', VW - 150, py + 30);
            } else {
                ctx.fillStyle = 'rgba(62,167,224,.12)'; ctx.fillRect(80, py, VW - 160, VH - py - 40);
                ctx.strokeStyle = '#3EA7E0'; ctx.lineWidth = 2.5; ctx.beginPath(); ctx.moveTo(80, py); ctx.lineTo(VW - 80, py); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 16px Inter, sans-serif'; ctx.textAlign = 'left'; ctx.fillText('water surface', VW - 230, py - 12);
                ctx.textAlign = 'center'; ctx.fillText('air', VW / 2, py - 40); ctx.fillText('water', VW / 2, py + 40);
            }

            // fixed rays
            rays.forEach((ry) => {
                const [ex, ey] = dirPt(px, py, ry.angle, R);
                ctx.strokeStyle = ry.dashed ? faint : ink; ctx.lineWidth = ry.dashed ? 1.6 : 2.4; ctx.setLineDash(ry.dashed ? [6, 5] : []);
                ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(ex, ey); ctx.stroke(); ctx.setLineDash([]);
                if (!ry.dashed) { const mid = dirPt(px, py, ry.angle, R * 0.55); head(ctx, mid[0], mid[1], ry.toPivot ? ry.angle + 180 : ry.angle, ink); }
                ctx.fillStyle = ry.dashed ? faint : ink; ctx.font = '600 15px Inter, sans-serif'; ctx.textAlign = 'center';
                const [lx, ly] = dirPt(px, py, ry.angle, R + 26); ctx.fillText(ry.label, lx, ly);
            });

            // picked option highlight
            const p = stRef.current;
            if (p) {
                const col = p.correct ? acc : '#fb7185';
                if (p.arc) {
                    const [a, b] = p.arc; ctx.strokeStyle = col; ctx.lineWidth = 4; ctx.beginPath();
                    const steps = 30; for (let i = 0; i <= steps; i++) { const t = a + (b - a) * i / steps; const [ax, ay] = dirPt(px, py, t, 46); ctx[i ? 'lineTo' : 'moveTo'](ax, ay); } ctx.stroke();
                    const [mx, my] = dirPt(px, py, (a + b) / 2, 68); ctx.fillStyle = col; ctx.font = '700 20px "JetBrains Mono", monospace'; ctx.textAlign = 'center'; ctx.fillText(p.label, mx, my);
                } else if (p.ray) {
                    const [ex, ey] = dirPt(px, py, p.ray.angle, R); ctx.strokeStyle = col; ctx.lineWidth = 3.5;
                    ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(ex, ey); ctx.stroke();
                    const mid = dirPt(px, py, p.ray.angle, R * 0.6); head(ctx, mid[0], mid[1], p.ray.angle, col);
                    const [lx, ly] = dirPt(px, py, p.ray.angle, R + 24); ctx.fillStyle = col; ctx.font = '700 20px "JetBrains Mono", monospace'; ctx.textAlign = 'center'; ctx.fillText(p.label, lx, ly);
                }
            }
            // pivot
            ctx.fillStyle = ink; ctx.beginPath(); ctx.arc(px, py, 4, 0, 6.283); ctx.fill();
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [surface, rays]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 9' }}>
                    <span className="cw-badge">{surface === 'mirror' ? 'Reflection' : 'Refraction'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Click each answer'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>Click each answer to see it highlighted on the diagram.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this diagram to my notes</button>)}
            </div>
        </div>
    );
}
