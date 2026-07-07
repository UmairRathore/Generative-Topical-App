import React, { useEffect, useMemo, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: beam_balance ─────────────────────────────────────────────────────
// Principle of moments. A uniform plank on two end supports (X, Y) carries a
// draggable load (the "child"). Reaction forces at the supports are computed by
// taking moments and drawn as live arrows; a graph plots the support force vs
// the load's position. Config-driven, so it serves the whole moments family.
//
// Props:
//   config      : { L, W, P, x, loadLabel, subject } — plank length (m), plank
//                 weight (N), load weight (N), load distance from X (m)
//   onReady     : (api) => void    — { getState, setState } for notes
//   onAddToNote : (config) => void — "add to note" intent

const DEFAULTS = { L: 4.0, W: 300, P: 600, x: 0 };

// virtual stage + plank geometry (fit-contain, never crops)
const VW = 1000, VH = 560;
const PLANK_Y = 300, PLANK_TH = 20, GROUND_Y = 480;
const PLANK_L = 200, PLANK_R = 800, SPAN = PLANK_R - PLANK_L;

function model(st) {
    const { L, W, P, x } = st;
    const Fx = (W * (L / 2) + P * (L - x)) / L;   // moments about Y
    const Fy = W + P - Fx;
    return { Fx, Fy, distY: L - x };
}

const pxAt = (x, L) => PLANK_L + (Math.max(0, Math.min(L, x)) / L) * SPAN;

function arrow(ctx, x1, y1, x2, y2, color, w = 4) {
    const a = Math.atan2(y2 - y1, x2 - x1), head = 11 + w;
    ctx.strokeStyle = color; ctx.fillStyle = color; ctx.lineWidth = w; ctx.lineCap = 'round';
    ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(x2, y2);
    ctx.lineTo(x2 - head * Math.cos(a - 0.4), y2 - head * Math.sin(a - 0.4));
    ctx.lineTo(x2 - head * Math.cos(a + 0.4), y2 - head * Math.sin(a + 0.4));
    ctx.closePath(); ctx.fill();
}

function drawGraph(cv, st) {
    if (!cv) return;
    const { L, W, P } = st, M = model(st);
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const ctx = cv.getContext('2d');
    const w = cv.clientWidth || 300, h = 150;
    cv.width = w * dpr; cv.height = h * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, w, h);
    const pad = { l: 38, r: 12, t: 12, b: 26 }, gw = w - pad.l - pad.r, gh = h - pad.t - pad.b;
    const ink = cssVar('--ink-soft', '#A6C4B3'), line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
    const yMax = W + P;
    ctx.strokeStyle = line; ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(pad.l, pad.t); ctx.lineTo(pad.l, pad.t + gh); ctx.lineTo(pad.l + gw, pad.t + gh); ctx.stroke();
    ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
    ctx.fillText('load position (m from X) →', pad.l + gw / 2, h - 6);
    ctx.save(); ctx.translate(11, pad.t + gh / 2); ctx.rotate(-Math.PI / 2); ctx.fillText('force at X (N) →', 0, 0); ctx.restore();
    const X = (xm) => pad.l + (xm / L) * gw, Y = (F) => pad.t + gh - (F / yMax) * gh;
    // F_X line: linear in x
    ctx.strokeStyle = acc; ctx.lineWidth = 2.4; ctx.beginPath();
    for (let i = 0; i <= 40; i++) { const xm = (i / 40) * L, F = (W * (L / 2) + P * (L - xm)) / L; i ? ctx.lineTo(X(xm), Y(F)) : ctx.moveTo(X(xm), Y(F)); }
    ctx.stroke();
    // operating point
    const opx = X(st.x), opy = Y(M.Fx);
    ctx.strokeStyle = 'rgba(52,211,153,.4)'; ctx.setLineDash([2, 3]);
    ctx.beginPath(); ctx.moveTo(opx, opy); ctx.lineTo(opx, pad.t + gh); ctx.moveTo(opx, opy); ctx.lineTo(pad.l, opy); ctx.stroke(); ctx.setLineDash([]);
    ctx.fillStyle = acc; ctx.strokeStyle = '#fff'; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.arc(opx, opy, 6, 0, 6.283); ctx.fill(); ctx.stroke();
}

export default function BeamBalance({ config = {}, onReady, onAddToNote }) {
    const [st, setSt] = useState({ ...DEFAULTS, ...config });
    const stRef = useRef(st); stRef.current = st;
    const set = (k, v) => setSt((s) => ({ ...s, [k]: +v }));
    const M = useMemo(() => model(st), [st]);
    const loadLabel = config.loadLabel || 'child';

    const stageRef = useRef(null);
    const graphRef = useRef(null);
    const geomRef = useRef({ scale: 1, ox: 0, oy: 0 });
    const dragRef = useRef(false);

    useEffect(() => {
        onReady && onReady({ getState: () => stRef.current, setState: (s) => setSt((x) => ({ ...x, ...s })) });
    }, [onReady]);

    // scene animation loop
    useEffect(() => {
        const cv = stageRef.current, host = cv.parentElement;
        const ctx = cv.getContext('2d');
        let dpr = 1, raf = 0;
        const motes = [];
        for (let i = 0; i < 34; i++) motes.push({ x: Math.random() * VW, y: Math.random() * VH, r: 0.6 + Math.random() * 1.6, s: 0.2 + Math.random() * 0.5, ph: Math.random() * 6.28 });

        const resize = () => {
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return;
            dpr = Math.min(window.devicePixelRatio || 1, 2);
            cv.width = w * dpr; cv.height = h * dpr;
            const scale = Math.min(w / VW, h / VH);
            geomRef.current = { scale, ox: (w - VW * scale) / 2, oy: (h - VH * scale) / 2 };
        };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const frame = (time) => {
            const s = stRef.current, m = model(s);
            const { scale, ox, oy } = geomRef.current;
            const forceCol = cssVar('--ok', '#34D399'), inkSoft = cssVar('--ink-soft', '#A6C4B3'), inkFaint = cssVar('--ink-faint', '#68877A');
            const WEIGHT = '#fb7185', WOOD = '#c79a5e', WOOD_D = '#8a6a39', STEEL = '#9aa6b8';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * scale, 0, 0, dpr * scale, ox * dpr, oy * dpr);

            // backdrop + faint grid
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);
            ctx.strokeStyle = 'rgba(160,200,175,.05)'; ctx.lineWidth = 1;
            for (let gx = 0; gx <= VW; gx += 50) { ctx.beginPath(); ctx.moveTo(gx, 0); ctx.lineTo(gx, VH); ctx.stroke(); }
            for (let gy = 0; gy <= VH; gy += 50) { ctx.beginPath(); ctx.moveTo(0, gy); ctx.lineTo(VW, gy); ctx.stroke(); }
            // drifting motes
            ctx.fillStyle = 'rgba(160,200,175,.10)';
            for (const p of motes) { p.y -= p.s; p.x += Math.sin(time * 0.0004 + p.ph) * 0.2; if (p.y < 0) { p.y = VH; p.x = Math.random() * VW; } ctx.globalAlpha = 0.4 + 0.4 * Math.sin(time * 0.001 + p.ph); ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.283); ctx.fill(); } ctx.globalAlpha = 1;

            // ground
            const gg = ctx.createLinearGradient(0, GROUND_Y, 0, VH); gg.addColorStop(0, '#1a2a1f'); gg.addColorStop(1, '#0c1a12');
            ctx.fillStyle = gg; ctx.fillRect(0, GROUND_Y, VW, VH - GROUND_Y);
            ctx.strokeStyle = 'rgba(160,200,175,.2)'; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(0, GROUND_Y); ctx.lineTo(VW, GROUND_Y); ctx.stroke();

            const cx = pxAt(s.x, s.L), midX = pxAt(s.L / 2, s.L), maxF = s.W + s.P;

            // supports (with glow ∝ reaction force)
            const drawSupport = (sx, F, label) => {
                const glow = 0.25 + 0.65 * (F / maxF);
                ctx.fillStyle = `rgba(52,211,153,${0.10 + 0.18 * (F / maxF)})`;
                ctx.beginPath(); ctx.arc(sx, PLANK_Y, 20 + 26 * (F / maxF), 0, 6.283); ctx.fill();
                ctx.fillStyle = STEEL; ctx.strokeStyle = '#c3ccd8'; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.moveTo(sx, PLANK_Y + PLANK_TH / 2); ctx.lineTo(sx - 26, GROUND_Y); ctx.lineTo(sx + 26, GROUND_Y); ctx.closePath(); ctx.fill(); ctx.stroke();
                ctx.globalAlpha = glow; ctx.fillStyle = forceCol; ctx.beginPath(); ctx.arc(sx, PLANK_Y, 5, 0, 6.283); ctx.fill(); ctx.globalAlpha = 1;
                ctx.fillStyle = inkSoft; ctx.font = '700 20px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(label, sx, GROUND_Y + 26);
            };
            drawSupport(PLANK_L, m.Fx, 'X');
            drawSupport(PLANK_R, m.Fy, 'Y');

            // plank
            ctx.save();
            const pg = ctx.createLinearGradient(0, PLANK_Y, 0, PLANK_Y + PLANK_TH); pg.addColorStop(0, WOOD); pg.addColorStop(1, WOOD_D);
            ctx.fillStyle = pg; ctx.strokeStyle = '#5f4a29'; ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.roundRect(PLANK_L - 26, PLANK_Y, SPAN + 52, PLANK_TH, 5); ctx.fill(); ctx.stroke();
            ctx.strokeStyle = 'rgba(255,255,255,.12)'; ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(PLANK_L - 20, PLANK_Y + 5); ctx.lineTo(PLANK_R + 20, PLANK_Y + 5); ctx.stroke();
            ctx.restore();

            // dimension line (load distance from X)
            ctx.strokeStyle = inkFaint; ctx.lineWidth = 1; ctx.setLineDash([3, 4]);
            ctx.beginPath(); ctx.moveTo(PLANK_L, PLANK_Y + PLANK_TH + 8); ctx.lineTo(cx, PLANK_Y + PLANK_TH + 8); ctx.stroke(); ctx.setLineDash([]);
            ctx.fillStyle = inkFaint; ctx.font = '600 13px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            if (s.x > 0.05) ctx.fillText(s.x.toFixed(1) + ' m', (PLANK_L + cx) / 2, PLANK_Y + PLANK_TH + 24);

            // weight arrows (down): plank at centre, load at cx
            const wLen = (wt) => 22 + (wt / maxF) * 120;
            arrow(ctx, midX, PLANK_Y + PLANK_TH, midX, PLANK_Y + PLANK_TH + wLen(s.W), WEIGHT, 3.5);
            ctx.fillStyle = WEIGHT; ctx.font = '700 15px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            ctx.fillText(s.W + ' N', midX, PLANK_Y + PLANK_TH + wLen(s.W) + 18);
            arrow(ctx, cx, PLANK_Y + PLANK_TH, cx, PLANK_Y + PLANK_TH + wLen(s.P), WEIGHT, 4);
            ctx.fillStyle = WEIGHT; ctx.fillText(s.P + ' N', cx, PLANK_Y + PLANK_TH + wLen(s.P) + 18);

            // reaction arrows (up) with animated pulse
            const pulse = 1 + 0.04 * Math.sin(time * 0.005);
            const fLen = (F) => (28 + (F / maxF) * 150) * pulse;
            arrow(ctx, PLANK_L, PLANK_Y, PLANK_L, PLANK_Y - fLen(m.Fx), forceCol, 5);
            arrow(ctx, PLANK_R, PLANK_Y, PLANK_R, PLANK_Y - fLen(m.Fy), forceCol, 5);
            ctx.fillStyle = forceCol; ctx.font = '700 17px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            ctx.fillText('F=' + Math.round(m.Fx) + ' N', PLANK_L, PLANK_Y - fLen(m.Fx) - 10);
            ctx.fillText(Math.round(m.Fy) + ' N', PLANK_R, PLANK_Y - fLen(m.Fy) - 10);

            // the load — a little figure standing on the plank
            const bob = Math.sin(time * 0.003) * 2;
            const fx = cx, fy = PLANK_Y - 2 + bob;
            ctx.strokeStyle = '#7fd3ff'; ctx.fillStyle = '#7fd3ff'; ctx.lineWidth = 4; ctx.lineCap = 'round';
            ctx.beginPath(); ctx.arc(fx, fy - 46, 9, 0, 6.283); ctx.fill();               // head
            ctx.beginPath(); ctx.moveTo(fx, fy - 37); ctx.lineTo(fx, fy - 14); ctx.stroke(); // body
            const sway = Math.sin(time * 0.003) * 3;
            ctx.beginPath(); ctx.moveTo(fx, fy - 30); ctx.lineTo(fx - 11, fy - 20 + sway); ctx.moveTo(fx, fy - 30); ctx.lineTo(fx + 11, fy - 20 - sway); ctx.stroke(); // arms
            ctx.beginPath(); ctx.moveTo(fx, fy - 14); ctx.lineTo(fx - 9, fy); ctx.moveTo(fx, fy - 14); ctx.lineTo(fx + 9, fy); ctx.stroke(); // legs

            raf = requestAnimationFrame(frame);
        };
        raf = requestAnimationFrame(frame);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, []);

    // drag the load along the plank
    useEffect(() => {
        const cv = stageRef.current;
        const toMetres = (clientX) => {
            const rect = cv.getBoundingClientRect();
            const { scale, ox } = geomRef.current;
            const vx = (clientX - rect.left - ox) / scale;
            const L = stRef.current.L;
            return Math.max(0, Math.min(L, ((vx - PLANK_L) / SPAN) * L));
        };
        const move = (clientX) => { const m = Math.round(toMetres(clientX) / 0.5) * 0.5; setSt((s) => ({ ...s, x: m })); };
        const down = (e) => { dragRef.current = true; cv.parentElement.classList.add('is-dragging'); move(e.clientX); };
        const drag = (e) => { if (dragRef.current) { e.preventDefault(); move(e.clientX); } };
        const up = () => { dragRef.current = false; cv.parentElement.classList.remove('is-dragging'); };
        cv.addEventListener('pointerdown', down); window.addEventListener('pointermove', drag); window.addEventListener('pointerup', up);
        return () => { cv.removeEventListener('pointerdown', down); window.removeEventListener('pointermove', drag); window.removeEventListener('pointerup', up); };
    }, []);

    useEffect(() => { drawGraph(graphRef.current, st); }, [st]);
    useEffect(() => {
        const onResize = () => drawGraph(graphRef.current, stRef.current);
        window.addEventListener('resize', onResize); return () => window.removeEventListener('resize', onResize);
    }, []);

    const atX = st.x <= 0.05, atY = st.x >= st.L - 0.05;
    const flag = atX
        ? { kind: 'ok', node: <>The {loadLabel} is at <b>X</b>: taking moments about Y, F = <b>{Math.round(M.Fx)} N</b> — the largest it gets.</> }
        : atY
            ? { kind: 'warn', node: <>The {loadLabel} is at <b>Y</b>, right over that support, so it adds no moment about Y — F at X falls to <b>{Math.round(M.Fx)} N</b>.</> }
            : { kind: 'neutral', node: <>Drag the {loadLabel} to X and Y — the two positions the exam asks about — and read F at each.</> };

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage">
                    <span className="cw-badge">Live · drag the {loadLabel}</span>
                    <canvas ref={stageRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="Upward force at support X" value={Math.round(M.Fx)} unit="N"
                      sub={<>Force at Y: <b>{Math.round(M.Fy)} N</b> · load is <b>{M.distY.toFixed(1)} m</b> from Y</>} />
                <div className="cw-graph"><canvas ref={graphRef} /></div>
                <div className="cw-calc-formula" style={{ margin: 0 }}>
                    <div className="cw-calc-flabel">Moments about Y</div>
                    <div className="cw-calc-expr">F × {st.L.toFixed(1)} = {st.W}×{(st.L / 2).toFixed(1)} + {st.P}×{M.distY.toFixed(1)}</div>
                    <div className="cw-calc-expr cw-calc-sub">F = {Math.round(M.Fx)} N</div>
                </div>
                <Slider label={`${loadLabel[0].toUpperCase() + loadLabel.slice(1)}'s distance from X`} value={st.x} min={0} max={st.L} step={0.5}
                        tone="#7fd3ff" format={(v) => v.toFixed(1) + ' m'} onChange={(v) => set('x', v)} />
                <div className="cw-btnrow">
                    <button className="cw-btn cw-btn-ghost" onClick={() => set('x', 0)}>{loadLabel} at X</button>
                    <button className="cw-btn cw-btn-ghost" onClick={() => set('x', st.L)}>{loadLabel} at Y</button>
                </div>
                <Flag kind={flag.kind}>{flag.node}</Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote(stRef.current)}>📌 Save this diagram to my notes</button>
                )}
            </div>
        </div>
    );
}
