import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag, Button } from '../primitives.jsx';

// ── Widget: spring_lab ───────────────────────────────────────────────────────
// Bespoke Elastic Deformation hero simulator (5054 · 1.5.3). A live spring rig:
// hang half-newton loads, watch the coil stretch against a ruler, and see the
// load–extension graph draw itself point by point — straight through the
// proportional region, bending past the limit of proportionality (marked X).
// k = F / x is syllabus-required here, so the calc panel shows it explicitly.
// Spring parameters are illustrative scenario data.
//
// config: { spring } · Notes contract: getState/setState carry {spring, load}.

const SPRINGS = [
    { key: 'A', label: 'Spring A (soft)', k: 0.4, limit: 4, col: '#8fb4d9' },   // k in N/cm, limit of proportionality in N
    { key: 'B', label: 'Spring B (stiff)', k: 0.8, limit: 4.8, col: '#c9a45c' },
];
const STEP_N = 0.5;
const MAX_N = 6;
const X_MAX = 15;   // graph extension axis, cm
const Y_MAX = 6;    // graph load axis, N

// Extension model: linear to the limit of proportionality, then softer (the curve bends).
const extOf = (F, s) => (F <= s.limit ? F / s.k : s.limit / s.k + (F - s.limit) / (s.k * 0.45));

export default function SpringLab({ config = {}, onReady, onAddToNote }) {
    const [sprKey, setSprKey] = useState(SPRINGS.some((s) => s.key === config.spring) ? config.spring : 'A');
    const [load, setLoad] = useState(0);
    const [plotted, setPlotted] = useState({ A: [0], B: [0] });   // loads (N) with a recorded point
    const cvRef = useRef(null);
    const animRef = useRef({ ext: 0 });
    const st = useRef({}); st.current = { sprKey, load, plotted };

    const spr = SPRINGS.find((s) => s.key === sprKey);
    const ext = extOf(load, spr);
    const proportional = load <= spr.limit + 1e-9;

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, spring: st.current.sprKey, load: st.current.load }),
            setState: (s) => {
                if (s?.spring && SPRINGS.some((x) => x.key === s.spring)) setSprKey(s.spring);
                if (typeof s?.load === 'number') {
                    const L = Math.max(0, Math.min(MAX_N, Math.round(s.load / STEP_N) * STEP_N));
                    setLoad(L);
                    setPlotted((p) => ({ ...p, [s.spring || st.current.sprKey]: [...new Set([...(p[s.spring || st.current.sprKey] || []), L])] }));
                }
            },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf;
        const draw = () => {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return;
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
            const amber = '#FBBF24', rose = '#FB7185';
            const { sprKey: sk, load: L, plotted: pts } = st.current;
            const S = SPRINGS.find((x) => x.key === sk);
            const target = extOf(L, S);
            const a = animRef.current;
            a.ext += (target - a.ext) * 0.08;

            // ── Rig (left ~40%) ──
            const rigX = w * 0.2, topY = 26, natLen = 44;                 // px of unstretched coil
            const pxPerCm = (h - topY - 120) / (extOf(MAX_N, SPRINGS[0]) + 2);
            const coilLen = natLen + a.ext * pxPerCm;
            ctx.strokeStyle = ink; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(rigX - 42, topY); ctx.lineTo(rigX + 42, topY); ctx.stroke();   // clamp bar
            for (let i = -40; i < 42; i += 8) { ctx.beginPath(); ctx.moveTo(rigX + i, topY); ctx.lineTo(rigX + i - 6, topY - 8); ctx.stroke(); }
            // coil zigzag
            ctx.strokeStyle = S.col; ctx.lineWidth = 2.5;
            ctx.beginPath(); ctx.moveTo(rigX, topY);
            const nCoils = 10;
            for (let i = 0; i <= nCoils; i++) {
                const y = topY + (coilLen * i) / nCoils;
                ctx.lineTo(rigX + (i === 0 || i === nCoils ? 0 : i % 2 ? 13 : -13), y);
            }
            ctx.stroke();
            // pan + weights
            const panY = topY + coilLen;
            ctx.strokeStyle = ink; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(rigX, panY); ctx.lineTo(rigX, panY + 12); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(rigX - 22, panY + 12); ctx.lineTo(rigX + 22, panY + 12); ctx.stroke();
            const nW = Math.round(L / STEP_N);
            for (let i = 0; i < nW; i++) {
                ctx.fillStyle = i % 2 ? 'rgba(251,191,36,.75)' : 'rgba(251,191,36,.55)';
                ctx.beginPath(); ctx.roundRect(rigX - 15, panY + 14 + i * 8, 30, 7, 2); ctx.fill();
            }
            ctx.fillStyle = faint; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText(`${L.toFixed(1)} N`, rigX, panY + 14 + nW * 8 + 14);
            // ruler with natural-length zero + extension bracket
            const rulX = rigX + 58, zeroY = topY + natLen;
            ctx.strokeStyle = line; ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(rulX, topY); ctx.lineTo(rulX, h - 60); ctx.stroke();
            ctx.font = '600 8px "JetBrains Mono", monospace'; ctx.textAlign = 'left';
            for (let cm = 0; cm <= X_MAX; cm += 5) {
                const yy = zeroY + cm * pxPerCm;
                ctx.strokeStyle = line; ctx.beginPath(); ctx.moveTo(rulX, yy); ctx.lineTo(rulX + 8, yy); ctx.stroke();
                ctx.fillStyle = faint; ctx.fillText(String(cm), rulX + 11, yy + 2.5);
            }
            ctx.setLineDash([3, 3]); ctx.strokeStyle = faint;
            ctx.beginPath(); ctx.moveTo(rigX - 30, zeroY); ctx.lineTo(rulX, zeroY); ctx.stroke(); ctx.setLineDash([]);
            ctx.fillStyle = faint; ctx.font = '600 8.5px Inter, sans-serif';
            ctx.fillText('unloaded', rulX + 26, zeroY + 3);
            if (a.ext > 0.15) {
                const y1 = zeroY + a.ext * pxPerCm;
                ctx.strokeStyle = amber; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.moveTo(rulX + 16, zeroY); ctx.lineTo(rulX + 22, zeroY); ctx.lineTo(rulX + 22, y1); ctx.lineTo(rulX + 16, y1); ctx.stroke();
                ctx.fillStyle = amber; ctx.font = '700 10px "JetBrains Mono", monospace';
                ctx.fillText(`x = ${a.ext.toFixed(1)} cm`, rulX + 27, (zeroY + y1) / 2 + 3);
            }

            // ── Graph (right) ──
            const gx = w * 0.52, gy = 30, gw = w * 0.44, gh = h - 92;
            const X = (cm) => gx + (cm / X_MAX) * gw;
            const Y = (N) => gy + gh - (N / Y_MAX) * gh;
            ctx.strokeStyle = line; ctx.lineWidth = 1;
            for (let N = 0; N <= Y_MAX; N += 1) { ctx.beginPath(); ctx.moveTo(gx, Y(N)); ctx.lineTo(gx + gw, Y(N)); ctx.stroke(); }
            ctx.strokeStyle = ink; ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.moveTo(gx, gy); ctx.lineTo(gx, gy + gh); ctx.lineTo(gx + gw, gy + gh); ctx.stroke();
            ctx.fillStyle = faint; ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText('extension / cm →', gx + gw / 2, gy + gh + 26);
            ctx.save(); ctx.translate(gx - 26, gy + gh / 2); ctx.rotate(-Math.PI / 2);
            ctx.fillText('load / N →', 0, 0); ctx.restore();
            ctx.font = '600 8px "JetBrains Mono", monospace'; ctx.textAlign = 'right';
            for (let N = 0; N <= Y_MAX; N += 2) ctx.fillText(String(N), gx - 5, Y(N) + 2.5);
            ctx.textAlign = 'center';
            for (let cm = 0; cm <= X_MAX; cm += 5) ctx.fillText(String(cm), X(cm), gy + gh + 12);
            // true curve up to the highest plotted load (faint guide)
            const maxPlot = Math.max(...(pts[sk] || [0]));
            if (maxPlot > 0) {
                ctx.strokeStyle = 'rgba(160,200,175,.35)'; ctx.lineWidth = 1.5; ctx.beginPath();
                for (let F = 0; F <= maxPlot + 1e-9; F += 0.05) {
                    const px = X(extOf(F, S)), py = Y(F);
                    F === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
                }
                ctx.stroke();
            }
            // limit of proportionality X mark once passed
            if (maxPlot >= S.limit) {
                const lx = X(extOf(S.limit, S)), ly = Y(S.limit);
                ctx.strokeStyle = rose; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.moveTo(lx - 5, ly - 5); ctx.lineTo(lx + 5, ly + 5); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(lx - 5, ly + 5); ctx.lineTo(lx + 5, ly - 5); ctx.stroke();
                ctx.fillStyle = rose; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText('limit of proportionality', lx + 9, ly - 4);
            }
            // plotted points
            (pts[sk] || []).forEach((F) => {
                const px = X(extOf(F, S)), py = Y(F);
                ctx.fillStyle = F <= S.limit ? acc : amber;
                ctx.beginPath(); ctx.arc(px, py, 3.5, 0, Math.PI * 2); ctx.fill();
            });
            // live (easing) point
            ctx.strokeStyle = amber; ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.arc(X(a.ext), Y(L), 6, 0, Math.PI * 2); ctx.stroke();

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const setLoadAndPlot = (L) => {
        const clamped = Math.max(0, Math.min(MAX_N, +L.toFixed(1)));
        setLoad(clamped);
        setPlotted((p) => ({ ...p, [sprKey]: [...new Set([...(p[sprKey] || []), clamped])].sort((x, y) => x - y) }));
    };
    const kShown = load > 0 && proportional ? (load / ext).toFixed(2) : null;

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Load it · read it · plot it</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label={proportional ? 'proportional region' : 'PAST the limit of proportionality'}
                      value={kShown ? `k = ${load.toFixed(1)} / ${ext.toFixed(1)} = ${kShown}` : proportional ? '—' : 'k no longer applies'}
                      unit={kShown ? 'N/cm' : ''} tone={proportional ? 'acc' : 'warn'}
                      sub={proportional
                          ? <>doubling the load doubles the extension — one spring constant fits every point</>
                          : <>the line has begun to curve: equal extra loads now give <b>bigger</b> extra extensions</>} />
                <div className="cw-btnrow">
                    {SPRINGS.map((s) => (
                        <button key={s.key} className={'cw-btn ' + (s.key === sprKey ? 'cw-btn-save' : 'cw-btn-ghost')}
                                onClick={() => { setSprKey(s.key); setLoad(0); }}>
                            {s.label}
                        </button>
                    ))}
                </div>
                <div className="cw-btnrow">
                    <Button variant="save" onClick={() => setLoadAndPlot(load + STEP_N)}>+ Hang 0.5 N</Button>
                    <Button variant="ghost" onClick={() => setLoad(Math.max(0, +(load - STEP_N).toFixed(1)))}>− Remove 0.5 N</Button>
                    <Button variant="ghost" onClick={() => { setLoad(0); setPlotted((p) => ({ ...p, [sprKey]: [0] })); }}>↺ Clear points</Button>
                </div>
                <Flag kind="neutral">
                    Each load you hang records one point. Through the straight section the gradient is the
                    <b> spring constant, k = F / x</b>. Keep loading and watch the moment the graph stops being
                    a straight line — that point is the <b>limit of proportionality</b>.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, spring: sprKey, load })}>📌 Save this rig to my notes</button>
                )}
            </div>
        </div>
    );
}
