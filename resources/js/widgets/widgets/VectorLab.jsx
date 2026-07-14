import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag, Button } from '../primitives.jsx';

// ── Widget: vector_lab ───────────────────────────────────────────────────────
// Bespoke Scalars & Vectors hero simulator (5054 · 1.1 LO 8). A boat crossing a
// flowing river: its own velocity points straight across, the current pushes
// downstream at right angles, and the boat's REAL track is the resultant —
// drawn live behind it. Beside the river, the tip-to-tail right-angle triangle
// assembles itself with the Pythagoras calculation and the angle, so the
// graphical and by-calculation routes sit side by side. Values are scenario data.
//
// config: { boat, current } · Notes contract: getState/setState carry
// {boat, current, t}.

export default function VectorLab({ config = {}, onReady, onAddToNote }) {
    const [boat, setBoat] = useState(typeof config.boat === 'number' ? config.boat : 4);
    const [current, setCurrent] = useState(typeof config.current === 'number' ? config.current : 3);
    const [playing, setPlaying] = useState(true);
    const cvRef = useRef(null);
    const simRef = useRef({ t: 0, flow: 0 });
    const st = useRef({}); st.current = { boat, current, playing };

    const R = Math.sqrt(boat * boat + current * current);
    const angle = (Math.atan2(current, boat) * 180) / Math.PI;

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, boat: st.current.boat, current: st.current.current, t: simRef.current.t }),
            setState: (s) => {
                if (typeof s?.boat === 'number') setBoat(Math.max(1, Math.min(8, s.boat)));
                if (typeof s?.current === 'number') setCurrent(Math.max(0, Math.min(8, s.current)));
                simRef.current.t = typeof s?.t === 'number' ? s.t : 0;
            },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, last = performance.now();
        const draw = (now) => {
            const dt = Math.min(0.05, (now - last) / 1000); last = now;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return;
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
            const cyan = '#38BDF8', amber = '#FBBF24';
            const S = st.current;
            const sim = simRef.current;
            sim.flow += dt * S.current * 10;

            // ── River scene (left ~55%) ── boat goes UP the canvas (across), current pushes RIGHT
            const rx = 16, rw = w * 0.52, bankB = h - 34, bankT = 34;
            const grad = ctx.createLinearGradient(0, bankT, 0, bankB);
            grad.addColorStop(0, 'rgba(56,189,248,.10)'); grad.addColorStop(1, 'rgba(56,189,248,.20)');
            ctx.fillStyle = grad; ctx.fillRect(rx, bankT, rw, bankB - bankT);
            ctx.strokeStyle = ink; ctx.lineWidth = 2;
            [bankT, bankB].forEach((yy) => { ctx.beginPath(); ctx.moveTo(rx, yy); ctx.lineTo(rx + rw, yy); ctx.stroke(); });
            // drifting ripples show the current
            ctx.strokeStyle = 'rgba(160,200,175,.18)'; ctx.lineWidth = 1.5;
            for (let i = 0; i < 7; i++) {
                const xx = rx + ((sim.flow + i * (rw / 7)) % rw);
                const yy = bankT + 22 + (i * 53) % (bankB - bankT - 44);
                ctx.beginPath(); ctx.moveTo(xx, yy); ctx.quadraticCurveTo(xx + 7, yy - 3, xx + 14, yy); ctx.stroke();
            }
            // crossing takes W_m metres at v_boat; scale so the crossing fits
            const crossT = 10 / Math.max(0.5, S.boat);            // river 10 m wide
            if (S.playing) sim.t = (sim.t + dt * 0.55) % (crossT * 1.35);
            const tt = Math.min(sim.t, crossT);
            const fx = (mx) => rx + 30 + mx * ((rw - 90) / (crossT * 8 + 1e-9)) * 1;   // metres downstream -> px
            const pxPerM = (bankB - bankT - 24) / 10;
            const bxp = rx + 30 + S.current * tt * pxPerM * 0.9;
            const byp = bankB - 12 - S.boat * tt * pxPerM;
            // intended (straight-across) ghost path
            ctx.setLineDash([3, 5]); ctx.strokeStyle = faint; ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.moveTo(rx + 30, bankB - 12); ctx.lineTo(rx + 30, bankT + 10); ctx.stroke(); ctx.setLineDash([]);
            ctx.fillStyle = faint; ctx.font = '600 8.5px Inter, sans-serif'; ctx.textAlign = 'left';
            ctx.fillText('aimed here', rx + 6, bankT + 12);
            // real resultant track
            ctx.strokeStyle = amber; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(rx + 30, bankB - 12); ctx.lineTo(bxp, byp); ctx.stroke();
            // boat with heading arrows
            ctx.save(); ctx.translate(bxp, byp);
            ctx.fillStyle = cyan;
            ctx.beginPath(); ctx.moveTo(0, -12); ctx.lineTo(7, 8); ctx.lineTo(-7, 8); ctx.closePath(); ctx.fill();
            ctx.restore();
            const arrow = (x0, y0, dx, dy, col, label) => {
                ctx.strokeStyle = col; ctx.fillStyle = col; ctx.lineWidth = 2.5;
                ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x0 + dx, y0 + dy); ctx.stroke();
                const len = Math.hypot(dx, dy) || 1, ux = dx / len, uy = dy / len;
                ctx.beginPath(); ctx.moveTo(x0 + dx + ux * 8, y0 + dy + uy * 8);
                ctx.lineTo(x0 + dx - uy * 4, y0 + dy + ux * 4); ctx.lineTo(x0 + dx + uy * 4, y0 + dy - ux * 4); ctx.fill();
                ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(label, x0 + dx + ux * 22, y0 + dy + uy * 22 + 3);
            };
            arrow(bxp, byp, 0, -S.boat * 7, cyan, 'boat');
            if (S.current > 0.05) arrow(bxp, byp, S.current * 7, 0, acc, 'current');

            // ── Tip-to-tail triangle + calculation (right) ──
            const tx0 = w * 0.62, ty0 = h * 0.72, sc = Math.min(w * 0.3 / 8.5, h * 0.5 / 8.5);
            const RR = Math.sqrt(S.boat * S.boat + S.current * S.current);
            const ang = (Math.atan2(S.current, S.boat) * 180) / Math.PI;
            // boat leg (up), then current leg tip-to-tail (right), then resultant hypotenuse
            arrow(tx0, ty0, 0, -S.boat * sc, cyan, `${S.boat.toFixed(1)}`);
            arrow(tx0, ty0 - S.boat * sc, S.current * sc, 0, acc, `${S.current.toFixed(1)}`);
            arrow(tx0, ty0, S.current * sc, -S.boat * sc, amber, '');
            // right-angle mark
            ctx.strokeStyle = faint; ctx.lineWidth = 1.5;
            ctx.strokeRect(tx0 + 2, ty0 - S.boat * sc, 9, 9);
            ctx.fillStyle = amber; ctx.font = '700 11px "JetBrains Mono", monospace'; ctx.textAlign = 'left';
            ctx.fillText(`R = √(${S.boat.toFixed(1)}² + ${S.current.toFixed(1)}²)`, tx0 - 20, ty0 + 26);
            ctx.fillText(`   = ${RR.toFixed(2)} m/s`, tx0 - 20, ty0 + 44);
            ctx.fillStyle = faint; ctx.font = '600 9.5px Inter, sans-serif';
            ctx.fillText(`${ang.toFixed(0)}° off the aimed heading`, tx0 - 20, ty0 + 62);
            ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText('tip-to-tail — the resultant closes the triangle', tx0 + S.current * sc * 0.5, ty0 - S.boat * sc - 16);

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Two vectors at right angles</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="resultant velocity (magnitude AND direction)"
                      value={R.toFixed(2)} unit="m/s" tone="acc"
                      sub={<>√({boat.toFixed(1)}² + {current.toFixed(1)}²) — pointing <b>{angle.toFixed(0)}°</b> downstream
                          of where the boat is aimed</>} />
                <Slider label="boat speed (aimed straight across)" value={boat} min={1} max={8} step={0.5}
                        onChange={(x) => { setBoat(x); simRef.current.t = 0; }} format={(x) => `${x.toFixed(1)} m/s`} />
                <Slider label="river current (at right angles)" value={current} min={0} max={8} step={0.5}
                        onChange={(x) => { setCurrent(x); simRef.current.t = 0; }} format={(x) => `${x.toFixed(1)} m/s`} />
                <div className="cw-btnrow">
                    <Button variant="save" onClick={() => setPlaying(!playing)}>{playing ? '⏸ Pause' : '▶ Cross'}</Button>
                    <Button variant="ghost" onClick={() => { simRef.current.t = 0; }}>↺ Restart crossing</Button>
                </div>
                <Flag kind="neutral">
                    Set the current to <b>0</b> and the boat lands where it aimed. Bring the current up and watch the amber
                    track tilt — the boat still points straight across, but it <b>moves</b> along the resultant. Try 4 and 3:
                    the triangle answers 5, by drawing or by Pythagoras.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, boat, current })}>📌 Save this crossing to my notes</button>
                )}
            </div>
        </div>
    );
}
