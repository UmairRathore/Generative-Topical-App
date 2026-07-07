import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: ac_generator ──────────────────────────────────────────────────────
// A rotating coil between magnet poles, its induced EMF drawn live on an
// oscilloscope. The trace is a sine; three points are marked (zero-crossing, peak,
// trough). A coil rotates in sync: EMF is zero when the coil plane is PERPENDICULAR
// to the field (90°) and maximum when the plane is PARALLEL to the field (0°/180°).
// The MCQ Options probe assigns the plane-field angle to points 1/2/3 and checks it.
// Relationship used: trace phase ψ from point 1, EMF ∝ sin ψ, plane-field angle φ = 90° + ψ.
// config: {
//   points:[{label:'1', psi:0}, {label:'2', psi:90}, {label:'3', psi:270}],  // phase (deg) of each marked point
//   optionsLead, hint,
//   options:[{label, angles:[a1,a2,a3], correct, note}]   // plane-field angle at points 1,2,3
// }

const VW = 1040, VH = 480;
const norm360 = (a) => ((a % 360) + 360) % 360;

export default function AcGenerator({ config = {}, onReady, onAddToNote }) {
    const points = config.points || [{ label: '1', psi: 0 }, { label: '2', psi: 90 }, { label: '3', psi: 270 }];
    const [picked, setPicked] = useState(null);
    const [playing, setPlaying] = useState(true);
    const stRef = useRef({ picked, playing }); stRef.current = { picked, playing };
    const psiRef = useRef(0);
    const cvRef = useRef(null);

    useEffect(() => { onReady && onReady({ getState: () => ({ psi: psiRef.current }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0, last = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const draw = (ts) => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            if (!last) last = ts; const dt = (ts - last) / 1000; last = ts;
            const { playing: pl } = stRef.current;
            if (pl) psiRef.current = norm360(psiRef.current + dt * 60); // 60°/s
            const psi = psiRef.current;
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399'), bad = '#fb7185', warn = '#fbbf24';
            const p = stRef.current.picked;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bgc = ctx.createLinearGradient(0, 0, 0, VH); bgc.addColorStop(0, '#0a1622'); bgc.addColorStop(1, '#0c1a12'); ctx.fillStyle = bgc; ctx.fillRect(0, 0, VW, VH);

            // ── left: generator (field vertical N→S downward) ──
            const gx = 175, gy = VH / 2, R = 120;
            ctx.fillStyle = 'rgba(251,113,133,.5)'; ctx.fillRect(gx - 130, gy - 175, 260, 46);
            ctx.fillStyle = 'rgba(96,165,250,.5)'; ctx.fillRect(gx - 130, gy + 129, 260, 46);
            ctx.fillStyle = '#0a1622'; ctx.font = '700 26px Georgia, serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillText('N', gx, gy - 152); ctx.fillText('S', gx, gy + 152);
            ctx.strokeStyle = faint; ctx.lineWidth = 1.6; ctx.fillStyle = faint;
            for (let k = -2; k <= 2; k++) { const x = gx + k * 52; ctx.beginPath(); ctx.moveTo(x, gy - 122); ctx.lineTo(x, gy + 122); ctx.stroke(); ctx.beginPath(); ctx.moveTo(x, gy + 118); ctx.lineTo(x - 5, gy + 106); ctx.lineTo(x + 5, gy + 106); ctx.closePath(); ctx.fill(); }

            // coil shown edge-on: plane-field angle φ = 90 + ψ. Draw the coil plane as a rotating bar.
            const phi = norm360(90 + psi);
            const a = phi * Math.PI / 180;
            // plane direction vector (the coil plane line); field is vertical, so angle from vertical = phi
            const dx = Math.sin(a), dy = -Math.cos(a);
            ctx.strokeStyle = warn; ctx.lineWidth = 7; ctx.lineCap = 'round';
            ctx.beginPath(); ctx.moveTo(gx - dx * R, gy - dy * R); ctx.lineTo(gx + dx * R, gy + dy * R); ctx.stroke(); ctx.lineCap = 'butt';
            ctx.fillStyle = warn; ctx.beginPath(); ctx.arc(gx - dx * R, gy - dy * R, 7, 0, 6.283); ctx.fill(); ctx.beginPath(); ctx.arc(gx + dx * R, gy + dy * R, 7, 0, 6.283); ctx.fill();
            ctx.fillStyle = ink; ctx.font = '600 14px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'alphabetic';
            ctx.fillText('coil (edge-on)', gx, gy + 205);
            ctx.fillStyle = warn; ctx.font = '700 15px "JetBrains Mono", monospace';
            ctx.fillText(`plane–field angle = ${Math.round(phi)}°`, gx, gy - 200);

            // ── right: oscilloscope ──
            const ox = 400, oy = 40, ow = VW - ox - 40, oh = VH - 80, mid = oy + oh / 2, amp = oh * 0.4;
            ctx.fillStyle = 'rgba(52,211,153,.04)'; ctx.fillRect(ox, oy, ow, oh);
            ctx.strokeStyle = 'rgba(166,196,179,.18)'; ctx.lineWidth = 1;
            for (let i = 0; i <= 8; i++) { const x = ox + ow * i / 8; ctx.beginPath(); ctx.moveTo(x, oy); ctx.lineTo(x, oy + oh); ctx.stroke(); }
            for (let i = 0; i <= 4; i++) { const y = oy + oh * i / 4; ctx.beginPath(); ctx.moveTo(ox, y); ctx.lineTo(ox + ow, y); ctx.stroke(); }
            ctx.strokeStyle = ink; ctx.lineWidth = 1.4; ctx.beginPath(); ctx.moveTo(ox, mid); ctx.lineTo(ox + ow, mid); ctx.stroke();
            // sine trace over one full cycle: EMF ∝ sin(ψ_x)
            ctx.strokeStyle = acc; ctx.lineWidth = 2.6; ctx.beginPath();
            for (let i = 0; i <= 360; i += 2) { const x = ox + ow * i / 360, y = mid - amp * Math.sin(i * Math.PI / 180); ctx[i ? 'lineTo' : 'moveTo'](x, y); }
            ctx.stroke();
            // marked points
            points.forEach((pt) => {
                const x = ox + ow * norm360(pt.psi) / 360, y = mid - amp * Math.sin(pt.psi * Math.PI / 180);
                const on = p && p.angles;
                ctx.fillStyle = ink; ctx.beginPath(); ctx.arc(x, y, 6, 0, 6.283); ctx.fill();
                ctx.fillStyle = ink; ctx.font = '700 16px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(pt.label, x, y + (pt.psi > 180 ? 26 : -14));
                if (on) {
                    const idx = +pt.label - 1, chosen = p.angles[idx], truth = norm360(90 + pt.psi);
                    const okAngle = norm360(chosen) === truth;
                    ctx.fillStyle = okAngle ? acc : bad; ctx.font = '700 13px "JetBrains Mono", monospace';
                    ctx.fillText(`${chosen}°`, x, y + (pt.psi > 180 ? 44 : -32));
                }
            });
            // live sweep dot
            const sx = ox + ow * psi / 360, sy = mid - amp * Math.sin(psi * Math.PI / 180);
            ctx.strokeStyle = warn; ctx.lineWidth = 1; ctx.setLineDash([3, 3]); ctx.beginPath(); ctx.moveTo(sx, oy); ctx.lineTo(sx, oy + oh); ctx.stroke(); ctx.setLineDash([]);
            ctx.fillStyle = warn; ctx.beginPath(); ctx.arc(sx, sy, 5, 0, 6.283); ctx.fill();
            ctx.fillStyle = faint; ctx.font = '600 13px Inter, sans-serif'; ctx.textAlign = 'left'; ctx.fillText('induced e.m.f.', ox + 8, oy + 18);

            if (p) {
                const col = p.correct ? acc : bad;
                ctx.fillStyle = col; ctx.font = '700 15px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(p.correct ? '✓ angles match the coil orientation at each point' : '✗ at least one angle is wrong', ox + ow / 2, oy + oh + 26);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [points]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '13 / 6' }}>
                    <span className="cw-badge">a.c. generator</span>
                    <canvas ref={cvRef} />
                </div>
                <div className="cw-btn-row">
                    <button className="cw-btn" onClick={() => setPlaying((v) => !v)}>{playing ? '⏸ Pause' : '▶ Play'} rotation</button>
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Plane–field angle at points 1, 2, 3?'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>e.m.f. is <strong>zero</strong> when the coil plane is <em>perpendicular</em> to the field (90°) and <strong>maximum</strong> when the plane is <em>parallel</em> to the field (0° or 180°).</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
