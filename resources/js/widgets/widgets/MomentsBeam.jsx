import React, { useEffect, useMemo, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag, Options } from '../primitives.jsx';

// ── Widget: moments_beam ─────────────────────────────────────────────────────
// General "take moments about a pivot" diagram. A rigid beam with a fulcrum/hinge
// at any position and several up/down forces at set distances. One force can be
// the UNKNOWN — solved from ΣM(pivot)=0. Draggable forces let you explore the
// balance. Covers trapdoors, seesaws, hinged beams, balanced metre rules, etc.
//
// config: { length, pivotAt, unit, prompt, subject, result:{label,unit}, note,
//           forces:[{ key, at, magnitude, dir:'up'|'down', label, movable?, unknown? }] }

const VW = 1000, VH = 520, BEAM_Y = 268, BEAM_TH = 16, GROUND_Y = 430, BEAM_L = 150, BEAM_R = 850, SPAN = BEAM_R - BEAM_L;
const pxAt = (m, len) => BEAM_L + (Math.max(0, Math.min(len, m)) / len) * SPAN;

function solve(forces, pivotAt) {
    let known = 0, unknown = null;
    forces.forEach((f) => {
        const r = f.at - pivotAt, sign = f.dir === 'up' ? 1 : -1;
        if (f.unknown) unknown = { r, sign };
        else known += r * sign * f.magnitude;
    });
    let mag = null;
    if (unknown && unknown.r !== 0) mag = Math.abs(-known / (unknown.r * unknown.sign));
    const resolved = forces.map((f) => ({ ...f, magnitude: f.unknown ? (mag ?? 0) : f.magnitude }));
    let cw = 0, acw = 0;
    resolved.forEach((f) => {
        const m = (f.at - pivotAt) * (f.dir === 'up' ? 1 : -1) * f.magnitude;
        if (m > 0) acw += m; else cw += -m;
    });
    return { resolved, unknownMag: mag, cw, acw };
}

function arrow(ctx, x1, y1, x2, y2, color, w = 4) {
    const a = Math.atan2(y2 - y1, x2 - x1), head = 10 + w;
    ctx.strokeStyle = color; ctx.fillStyle = color; ctx.lineWidth = w; ctx.lineCap = 'round';
    ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(x2, y2);
    ctx.lineTo(x2 - head * Math.cos(a - 0.4), y2 - head * Math.sin(a - 0.4));
    ctx.lineTo(x2 - head * Math.cos(a + 0.4), y2 - head * Math.sin(a + 0.4));
    ctx.closePath(); ctx.fill();
}

export default function MomentsBeam({ config = {}, onReady, onAddToNote }) {
    const { length = 4, pivotAt = 0, unit = 'N', prompt, subject, result = {}, note } = config;
    const [forces, setForces] = useState(() => (config.forces || []).map((f) => ({ ...f })));
    const fRef = useRef(forces); fRef.current = forces;
    const [probe, setProbe] = useState(null);   // trying an option's force (N), or null = show the worked answer

    const M = useMemo(() => {
        const base = solve(forces, pivotAt);
        if (probe == null) return base;
        // override the unknown with the tried value and recompute the balance
        const resolved = forces.map((f) => ({ ...f, magnitude: f.unknown ? probe : f.magnitude }));
        let cw = 0, acw = 0;
        resolved.forEach((f) => { const m = (f.at - pivotAt) * (f.dir === 'up' ? 1 : -1) * f.magnitude; if (m > 0) acw += m; else cw += -m; });
        return { resolved, unknownMag: probe, cw, acw, solvedMag: base.unknownMag };
    }, [forces, pivotAt, probe]);
    const mRef = useRef(M); mRef.current = M;

    const stageRef = useRef(null);
    const geomRef = useRef({ scale: 1, ox: 0, oy: 0 });
    const dragRef = useRef(-1);

    const editable = forces.map((f, i) => ({ f, i })).filter((x) => x.f.movable);

    useEffect(() => {
        onReady && onReady({ getState: () => ({ ...config, forces: fRef.current }), setState: (s) => s?.forces && setForces(s.forces) });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = stageRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0;
        const resize = () => {
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return;
            dpr = Math.min(window.devicePixelRatio || 1, 2);
            cv.width = w * dpr; cv.height = h * dpr;
            const scale = Math.min(w / VW, h / VH);
            geomRef.current = { scale, ox: (w - VW * scale) / 2, oy: (h - VH * scale) / 2 };
        };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const frame = (time) => {
            const flist = mRef.current.resolved;
            const { scale, ox, oy } = geomRef.current;
            const forceUp = cssVar('--ok', '#34D399'), inkSoft = cssVar('--ink-soft', '#A6C4B3'), inkFaint = cssVar('--ink-faint', '#68877A');
            const DOWN = '#fb7185', STEEL = '#9aa6b8', WOOD = '#c79a5e', WOOD_D = '#8a6a39';
            const maxMag = Math.max(1, ...flist.map((f) => f.magnitude));

            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * scale, 0, 0, dpr * scale, ox * dpr, oy * dpr);

            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);
            ctx.strokeStyle = 'rgba(160,200,175,.05)'; ctx.lineWidth = 1;
            for (let g = 0; g <= VW; g += 50) { ctx.beginPath(); ctx.moveTo(g, 0); ctx.lineTo(g, VH); ctx.stroke(); }
            for (let g = 0; g <= VH; g += 50) { ctx.beginPath(); ctx.moveTo(0, g); ctx.lineTo(VW, g); ctx.stroke(); }

            const px = pxAt(pivotAt, length);
            // beam
            const pg = ctx.createLinearGradient(0, BEAM_Y, 0, BEAM_Y + BEAM_TH); pg.addColorStop(0, WOOD); pg.addColorStop(1, WOOD_D);
            ctx.fillStyle = pg; ctx.strokeStyle = '#5f4a29'; ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.roundRect(BEAM_L - 20, BEAM_Y, SPAN + 40, BEAM_TH, 5); ctx.fill(); ctx.stroke();

            // fulcrum / pivot
            ctx.fillStyle = STEEL; ctx.strokeStyle = '#c3ccd8'; ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.moveTo(px, BEAM_Y + BEAM_TH); ctx.lineTo(px - 22, GROUND_Y); ctx.lineTo(px + 22, GROUND_Y); ctx.closePath(); ctx.fill(); ctx.stroke();
            ctx.fillStyle = 'rgba(52,211,153,.14)'; ctx.beginPath(); ctx.arc(px, BEAM_Y + BEAM_TH / 2, 9, 0, 6.283); ctx.fill();
            ctx.fillStyle = inkFaint; ctx.font = '700 15px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            ctx.fillText('pivot', px, GROUND_Y + 22);

            // forces
            flist.forEach((f) => {
                const fx = pxAt(f.at, length), up = f.dir === 'up';
                const len = 26 + (f.magnitude / maxMag) * 130;
                const col = up ? forceUp : DOWN;
                if (up) arrow(ctx, fx, BEAM_Y, fx, BEAM_Y - len, col, f.unknown ? 5 : 4);
                else arrow(ctx, fx, BEAM_Y + BEAM_TH, fx, BEAM_Y + BEAM_TH + len, col, f.unknown ? 5 : 4);
                ctx.fillStyle = col; ctx.font = '700 15px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                const ly = up ? BEAM_Y - len - 10 : BEAM_Y + BEAM_TH + len + 18;
                ctx.fillText(`${f.label}: ${Math.round(f.magnitude)} ${unit}`, fx, ly);
                if (f.movable) { ctx.fillStyle = inkFaint; ctx.font = '600 11px Inter, sans-serif'; ctx.fillText('drag ↔', fx, up ? BEAM_Y - len - 26 : BEAM_Y + BEAM_TH + len + 32); }
                // distance tick from pivot
                if (Math.abs(f.at - pivotAt) > 0.01) {
                    ctx.strokeStyle = inkFaint; ctx.setLineDash([3, 4]); ctx.lineWidth = 1;
                    ctx.beginPath(); ctx.moveTo(px, BEAM_Y + BEAM_TH + 6); ctx.lineTo(fx, BEAM_Y + BEAM_TH + 6); ctx.stroke(); ctx.setLineDash([]);
                    ctx.fillStyle = inkFaint; ctx.font = '600 12px "JetBrains Mono", monospace';
                    ctx.fillText(Math.abs(f.at - pivotAt).toFixed(1) + ' m', (px + fx) / 2, BEAM_Y + BEAM_TH + 20);
                }
            });

            raf = requestAnimationFrame(frame);
        };
        raf = requestAnimationFrame(frame);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [pivotAt, length, unit]);

    // drag movable forces horizontally
    useEffect(() => {
        const cv = stageRef.current;
        const toM = (clientX) => {
            const rect = cv.getBoundingClientRect(), { scale, ox } = geomRef.current;
            const vx = (clientX - rect.left - ox) / scale;
            return Math.max(0, Math.min(length, ((vx - BEAM_L) / SPAN) * length));
        };
        const down = (e) => {
            const m = toM(e.clientX);
            let best = -1, bestD = 0.6;
            fRef.current.forEach((f, i) => { if (f.movable) { const d = Math.abs(f.at - m); if (d < bestD) { bestD = d; best = i; } } });
            if (best >= 0) { dragRef.current = best; cv.parentElement.classList.add('is-dragging'); }
        };
        const move = (e) => {
            if (dragRef.current < 0) return; e.preventDefault();
            const m = Math.round(toM(e.clientX) / 0.5) * 0.5, i = dragRef.current;
            setForces((fs) => fs.map((f, j) => (j === i ? { ...f, at: m } : f)));
        };
        const up = () => { dragRef.current = -1; cv.parentElement.classList.remove('is-dragging'); };
        cv.addEventListener('pointerdown', down); window.addEventListener('pointermove', move); window.addEventListener('pointerup', up);
        return () => { cv.removeEventListener('pointerdown', down); window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); };
    }, [length]);

    const setAt = (i, v) => setForces((fs) => fs.map((f, j) => (j === i ? { ...f, at: +v } : f)));
    const balanced = Math.abs(M.cw - M.acw) < 0.5;
    const maxMoment = Math.max(1, M.cw, M.acw);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage">
                    <span className="cw-badge">Live · moments about the pivot</span>
                    <canvas ref={stageRef} />
                </div>
            </div>
            <div className="cw-controls">
                {M.unknownMag != null && (
                    <Stat label={probe != null ? 'You are trying' : (result.label || 'Unknown force')} value={Math.round(M.unknownMag)} unit={result.unit || unit} tone="acc" />
                )}
                <div className="cw-moment-bars">
                    <div className="cw-mb-row"><span>anticlockwise</span><div className="cw-mb-track"><div className="cw-mb-fill acw" style={{ width: (M.acw / maxMoment * 100) + '%' }} /></div><b>{Math.round(M.acw)}</b></div>
                    <div className="cw-mb-row"><span>clockwise</span><div className="cw-mb-track"><div className="cw-mb-fill cw" style={{ width: (M.cw / maxMoment * 100) + '%' }} /></div><b>{Math.round(M.cw)}</b></div>
                </div>
                {config.options?.length > 0 && (
                    <Options options={config.options} lead="Try each answer on the beam" onPick={(o) => setProbe(o.force ?? null)} />
                )}
                {probe != null && (
                    <button className="cw-btn cw-btn-ghost" onClick={() => setProbe(null)}>↺ Back to the worked answer</button>
                )}
                {editable.map(({ f, i }) => (
                    <Slider key={f.key} label={`${f.label} position`} value={f.at} min={0} max={length} step={0.5}
                            tone="#7fd3ff" format={(v) => v.toFixed(1) + ' m'} onChange={(v) => setAt(i, v)} />
                ))}
                <Flag kind={balanced ? 'ok' : 'neutral'}>
                    {balanced
                        ? <>Balanced: anticlockwise = clockwise = <b>{Math.round(M.acw)} {unit}·m</b>. {note}</>
                        : <>Not balanced — anticlockwise {Math.round(M.acw)} vs clockwise {Math.round(M.cw)} {unit}·m.</>}
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, forces })}>📌 Save this diagram to my notes</button>
                )}
            </div>
        </div>
    );
}
