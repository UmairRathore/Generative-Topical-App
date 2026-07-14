import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag, Button } from '../primitives.jsx';

// ── Widget: stability_lab ────────────────────────────────────────────────────
// Bespoke Centre of Gravity hero simulator (5054 · 1.5.6). Two modes:
//   FIND — the plumb-line lamina experiment performed: hang an irregular
//   lamina from a pin, it settles with its CG below the pin, mark the plumb
//   line, re-hang from another pin; the marked lines cross at the CG.
//   TILT — a crate on a tilting platform: the vertical through the CG is drawn
//   live; the crate rights itself while the line stays inside the base and
//   topples once it crosses the base edge. Qualitative; no formula displayed.
//
// config: { mode } · Notes contract: getState/setState carry
// {mode, marks, tilt, cgH, baseW}.

// Irregular lamina in local coords; uniform sheet -> CG = area centroid.
const LAMINA = [[-70, -52], [58, -70], [86, 6], [22, 74], [-84, 44]];
const PINS = [0, 1, 3];   // vertex indices usable as hang points (A, B, C)

const centroid = (pts) => {
    let a = 0, cx = 0, cy = 0;
    for (let i = 0; i < pts.length; i++) {
        const [x0, y0] = pts[i], [x1, y1] = pts[(i + 1) % pts.length];
        const c = x0 * y1 - x1 * y0;
        a += c; cx += (x0 + x1) * c; cy += (y0 + y1) * c;
    }
    a /= 2;
    return [cx / (6 * a), cy / (6 * a)];
};
const CG = centroid(LAMINA);

export default function StabilityLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(config.mode === 'tilt' ? 'tilt' : 'find');
    // find mode
    const [pin, setPin] = useState(0);                 // index into PINS
    const [marks, setMarks] = useState([]);            // pin indices whose plumb line is marked
    // tilt mode
    const [tilt, setTilt] = useState(10);
    const [cgH, setCgH] = useState(3);                 // CG height, arbitrary units 1-5
    const [baseW, setBaseW] = useState(3);             // base width, 1-5
    const cvRef = useRef(null);
    const animRef = useRef({ rot: 0, topple: 0 });
    const st = useRef({}); st.current = { mode, pin, marks, tilt, cgH, baseW };

    const critDeg = (Math.atan((baseW / 2) / cgH) * 180) / Math.PI;
    const topples = tilt > critDeg;

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, marks: st.current.marks, tilt: st.current.tilt, cgH: st.current.cgH, baseW: st.current.baseW }),
            setState: (s) => {
                if (s?.mode === 'find' || s?.mode === 'tilt') setMode(s.mode);
                if (Array.isArray(s?.marks)) setMarks(s.marks.filter((i) => i >= 0 && i < PINS.length));
                if (typeof s?.tilt === 'number') setTilt(Math.max(0, Math.min(40, s.tilt)));
                if (typeof s?.cgH === 'number') setCgH(Math.max(1, Math.min(5, s.cgH)));
                if (typeof s?.baseW === 'number') setBaseW(Math.max(1, Math.min(5, s.baseW)));
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
            const cyan = '#38BDF8', amber = '#FBBF24', rose = '#FB7185';
            const S = st.current;
            const a = animRef.current;

            if (S.mode === 'find') {
                // hang point settles so pin->CG points straight down
                const P = LAMINA[PINS[S.pin]];
                const target = Math.atan2(CG[0] - P[0], CG[1] - P[1]);   // rotate by -target
                let d = -target - a.rot;
                while (d > Math.PI) d -= 2 * Math.PI;
                while (d < -Math.PI) d += 2 * Math.PI;
                a.rot += d * 0.06;

                const hx = w * 0.42, hy = 40;
                const cos = Math.cos(a.rot), sin = Math.sin(a.rot);
                const toScreen = ([x, y]) => {
                    const dx = x - P[0], dy = y - P[1];
                    return [hx + dx * cos - dy * sin, hy + dx * sin + dy * cos];
                };
                // lamina
                ctx.fillStyle = 'rgba(56,189,248,.13)'; ctx.strokeStyle = cyan; ctx.lineWidth = 2;
                ctx.beginPath();
                LAMINA.forEach((pt, i) => { const [sx, sy] = toScreen(pt); i ? ctx.lineTo(sx, sy) : ctx.moveTo(sx, sy); });
                ctx.closePath(); ctx.fill(); ctx.stroke();
                // marked plumb chords (stored as lines through pin_i and CG in LOCAL frame)
                S.marks.forEach((mi) => {
                    const Q = LAMINA[PINS[mi]];
                    const dir = [CG[0] - Q[0], CG[1] - Q[1]];
                    const n = Math.hypot(...dir), ux = dir[0] / n, uy = dir[1] / n;
                    const A2 = toScreen([Q[0] - ux * 20, Q[1] - uy * 20]);
                    const B2 = toScreen([Q[0] + ux * 190, Q[1] + uy * 190]);
                    ctx.strokeStyle = amber; ctx.lineWidth = 1.5; ctx.setLineDash([5, 4]);
                    ctx.beginPath(); ctx.moveTo(A2[0], A2[1]); ctx.lineTo(B2[0], B2[1]); ctx.stroke(); ctx.setLineDash([]);
                });
                // pins
                PINS.forEach((vi, i) => {
                    const [sx, sy] = toScreen(LAMINA[vi]);
                    ctx.fillStyle = i === S.pin ? amber : faint;
                    ctx.beginPath(); ctx.arc(sx, sy, 4.5, 0, Math.PI * 2); ctx.fill();
                    ctx.font = '600 10px Inter, sans-serif'; ctx.fillStyle = i === S.pin ? amber : faint;
                    ctx.fillText('ABC'[i], sx + 10, sy + 3);
                });
                // support + live plumb line (bob)
                ctx.strokeStyle = ink; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.moveTo(hx - 40, hy - 12); ctx.lineTo(hx + 40, hy - 12); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(hx, hy - 12); ctx.lineTo(hx, hy); ctx.stroke();
                ctx.strokeStyle = 'rgba(251,191,36,.85)'; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.moveTo(hx, hy); ctx.lineTo(hx, hy + 215); ctx.stroke();
                ctx.fillStyle = amber;
                ctx.beginPath(); ctx.arc(hx, hy + 215, 6, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = faint; ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText('plumb line marks the vertical', hx + 14, hy + 212);
                // CG reveal once 2+ lines marked
                if (S.marks.length >= 2) {
                    const [gx, gy] = toScreen(CG);
                    const pulse = 3 + Math.sin(Date.now() / 300) * 1.2;
                    ctx.strokeStyle = acc; ctx.lineWidth = 2;
                    ctx.beginPath(); ctx.arc(gx, gy, 7 + pulse, 0, Math.PI * 2); ctx.stroke();
                    ctx.fillStyle = acc;
                    ctx.beginPath(); ctx.arc(gx, gy, 3, 0, Math.PI * 2); ctx.fill();
                    ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'left';
                    ctx.fillText('the lines cross at the centre of gravity', gx + 16, gy + 3);
                }
            } else {
                // ── TILT mode ──
                const cx = w * 0.5, groundY = h - 40;
                const th = (S.tilt * Math.PI) / 180;
                const crit = Math.atan((S.baseW / 2) / S.cgH);
                const over = th > crit;
                a.topple += ((over ? 1 : 0) - a.topple) * 0.05;
                // platform
                ctx.save();
                ctx.translate(cx, groundY);
                ctx.rotate(-th);
                ctx.strokeStyle = ink; ctx.lineWidth = 2.5;
                ctx.beginPath(); ctx.moveTo(-w * 0.34, 0); ctx.lineTo(w * 0.34, 0); ctx.stroke();
                // crate on the platform (units -> px)
                const u = Math.min(w, h) / 11;
                const bw = S.baseW * u, chg = S.cgH * u, bh = chg * 1.7;
                ctx.save();
                // extra topple rotation about the downhill (left) base corner
                ctx.translate(-bw / 2, 0);
                ctx.rotate(-a.topple * (Math.PI / 2 - 0.12));
                ctx.translate(bw / 2, 0);
                ctx.fillStyle = 'rgba(56,189,248,.13)'; ctx.strokeStyle = cyan; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.roundRect(-bw / 2, -bh, bw, bh, 6); ctx.fill(); ctx.stroke();
                // CG dot at height cgH
                ctx.fillStyle = acc;
                ctx.beginPath(); ctx.arc(0, -chg, 5, 0, Math.PI * 2); ctx.fill();
                ctx.font = '600 10px Inter, sans-serif'; ctx.fillStyle = acc; ctx.textAlign = 'left';
                ctx.fillText('CG', 9, -chg + 3);
                // vertical (world-down) line through CG — drawn in crate frame via inverse rotation
                const total = -th - a.topple * (Math.PI / 2 - 0.12);
                const dxw = Math.sin(total), dyw = Math.cos(total);   // world-down expressed in the crate frame
                ctx.strokeStyle = over ? rose : amber; ctx.lineWidth = 2; ctx.setLineDash([5, 4]);
                ctx.beginPath(); ctx.moveTo(0, -chg); ctx.lineTo(dxw * chg * 1.6, -chg + dyw * chg * 1.6); ctx.stroke(); ctx.setLineDash([]);
                // base extent markers
                ctx.strokeStyle = faint; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.moveTo(-bw / 2, 4); ctx.lineTo(-bw / 2, 12); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(bw / 2, 4); ctx.lineTo(bw / 2, 12); ctx.stroke();
                ctx.restore();
                ctx.restore();
                // captions
                ctx.fillStyle = faint; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(`platform tilt ${S.tilt.toFixed(0)}°`, cx, groundY + 24);
                ctx.font = '700 11.5px "JetBrains Mono", monospace';
                ctx.fillStyle = over ? rose : acc;
                ctx.fillText(over ? 'the vertical through the CG is OUTSIDE the base — it topples'
                                  : 'the vertical through the CG is inside the base — it rights itself', cx, 48);
            }

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const markLine = () => setMarks((m) => (m.includes(pin) ? m : [...m, pin]));

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{mode === 'find' ? 'Find the CG · plumb line' : 'Stability · CG over the base'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'find' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('find')}>🧷 Find the CG</button>
                    <button className={'cw-btn ' + (mode === 'tilt' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('tilt')}>⛰ Tilt test</button>
                </div>
                {mode === 'find' ? (
                    <>
                        <Stat label="plumb lines marked"
                              value={`${marks.length}`} tone={marks.length >= 2 ? 'acc' : undefined}
                              sub={marks.length >= 2
                                  ? <>the crossing point is the <b>centre of gravity</b> — every extra line passes through it too</>
                                  : <>one line only tells you the CG lies <b>somewhere along it</b> — hang from a second pin</>} />
                        <div className="cw-btnrow">
                            {PINS.map((_, i) => (
                                <button key={i} className={'cw-btn ' + (i === pin ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setPin(i)}>
                                    Hang from {'ABC'[i]}
                                </button>
                            ))}
                        </div>
                        <div className="cw-btnrow">
                            <Button variant="save" onClick={markLine}>✏ Mark this plumb line</Button>
                            <Button variant="ghost" onClick={() => setMarks([])}>↺ Clear lines</Button>
                        </div>
                        <Flag kind="neutral">
                            A freely hanging lamina settles with its centre of gravity <b>vertically below the pin</b> — that is why
                            the plumb line is the experiment: mark the vertical, re-hang from a different point, and the CG is where
                            the marked lines <b>cross</b>.
                        </Flag>
                    </>
                ) : (
                    <>
                        <Stat label={topples ? 'past the tipping point' : 'stable at this tilt'}
                              value={topples ? 'topples' : 'rights itself'} tone={topples ? 'warn' : 'acc'}
                              sub={<>lower the CG or widen the base and the same tilt becomes safe — <b>where</b> the weight acts decides, not how much</>} />
                        <Slider label="platform tilt" value={tilt} min={0} max={40} step={1} onChange={(x) => setTilt(x)} format={(x) => `${x.toFixed(0)}°`} />
                        <Slider label="height of the CG" value={cgH} min={1} max={5} step={0.25} onChange={(x) => setCgH(x)} format={(x) => x.toFixed(2)} />
                        <Slider label="width of the base" value={baseW} min={1} max={5} step={0.25} onChange={(x) => setBaseW(x)} format={(x) => x.toFixed(2)} />
                        <Flag kind="neutral">
                            Watch the dashed line from the CG: while it lands <b>inside the base</b> the crate falls back onto the
                            platform; the moment it crosses the base edge, the same weight tips it over. Racing cars are built low
                            and wide for exactly this reason.
                        </Flag>
                    </>
                )}
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, marks, tilt, cgH, baseW })}>📌 Save this setup to my notes</button>
                )}
            </div>
        </div>
    );
}
