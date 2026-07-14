import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: particle_lab ─────────────────────────────────────────────────────
// Bespoke Kinetic Particle Model hero simulator (5054 · 2.1.2). One chamber,
// four stories: (1) the particle structure of solid / liquid / gas; (2) motion
// vs temperature down to absolute zero at −273 °C, where the jiggling has
// least kinetic energy; (3) gas pressure as particle collisions with the
// walls - the gauge is driven by the same p ∝ T/V the collisions enact; and
// (4) squeeze the piston at constant temperature and watch the p–V point ride
// a hyperbola while p×V holds still: p1V1 = p2V2, live. Gauge units are
// arbitrary; the physics is qualitative by design.
//
// config: { state } · Notes contract: getState/setState carry {state, tC, vol}.

const STATES = ['solid', 'liquid', 'gas'];
const N = 42;

export default function ParticleLab({ config = {}, onReady, onAddToNote }) {
    const [stateKey, setStateKey] = useState(STATES.includes(config.state) ? config.state : 'gas');
    const [tC, setTC] = useState(20);              // °C, -273..400
    const [vol, setVol] = useState(1.0);           // relative volume 0.4..1.0 (gas piston)
    const cvRef = useRef(null);
    const partsRef = useRef(null);
    const st = useRef({}); st.current = { stateKey, tC, vol };

    const TK = tC + 273;
    const pGas = (TK / 293) / vol;                 // arbitrary units, 1.0 at 20 °C full volume

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, state: st.current.stateKey, tC: st.current.tC, vol: st.current.vol }),
            setState: (s) => {
                if (s?.state && STATES.includes(s.state)) setStateKey(s.state);
                if (typeof s?.tC === 'number') setTC(Math.max(-273, Math.min(400, s.tC)));
                if (typeof s?.vol === 'number') setVol(Math.max(0.4, Math.min(1.0, s.vol)));
            },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        if (!partsRef.current) {
            const rng = (i, k) => ((Math.sin(i * 127.1 + k * 311.7) * 43758.5453) % 1 + 1) % 1;
            partsRef.current = Array.from({ length: N }, (_, i) => ({
                x: rng(i, 1), y: rng(i, 2), vx: rng(i, 3) - 0.5, vy: rng(i, 4) - 0.5, ph: rng(i, 5) * 6.28,
            }));
        }
        let raf, t = 0;
        const draw = () => {
            t += 0.016;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return;
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
            const cyan = '#38BDF8', amber = '#FBBF24', rose = '#FB7185';
            const S = st.current, parts = partsRef.current;
            const TKn = S.tC + 273;
            const speed = Math.sqrt(Math.max(0, TKn) / 293);        // 1.0 at 20 °C

            // ── Chamber (left) ──
            const cx0 = 24, cy0 = 30, chH = h - 96;
            const fullW = w * 0.42;
            const isGas = S.stateKey === 'gas';
            const chW = isGas ? fullW * S.vol : fullW;
            ctx.strokeStyle = ink; ctx.lineWidth = 2.5;
            ctx.strokeRect(cx0, cy0, chW, chH);
            if (isGas && S.vol < 0.99) {
                ctx.fillStyle = 'rgba(160,200,175,.25)';
                ctx.fillRect(cx0 + chW, cy0, 8, chH);
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText('piston', cx0 + chW + 12, cy0 + 12);
            }
            const r = 4.5;
            if (S.stateKey === 'solid') {
                // lattice: fixed grid, vibration amplitude ∝ speed
                const cols = 7, rows = 6, gx = chW / (cols + 1), gy = chH / (rows + 1);
                for (let i = 0; i < cols * rows; i++) {
                    const c = i % cols, rr = Math.floor(i / cols);
                    const amp = 2.5 * speed;
                    const px = cx0 + gx * (c + 1) + Math.sin(t * 9 + i * 1.7) * amp;
                    const py = cy0 + gy * (rr + 1) + Math.cos(t * 8 + i * 2.3) * amp;
                    ctx.fillStyle = cyan;
                    ctx.beginPath(); ctx.arc(px, py, r, 0, Math.PI * 2); ctx.fill();
                }
            } else if (S.stateKey === 'liquid') {
                // jostling cluster in the lower half: particles wander slowly, stay close
                parts.forEach((p, i) => {
                    p.ph += 0.03 * speed;
                    const homeX = (i % 9 + 0.8) / 9.7, homeY = 0.55 + (Math.floor(i / 9) % 4 + 0.6) / 9;
                    p.x += ((homeX - p.x) * 0.02 + Math.sin(p.ph + i) * 0.004 * speed);
                    p.y += ((homeY - p.y) * 0.02 + Math.cos(p.ph * 1.3 + i) * 0.004 * speed);
                    ctx.fillStyle = cyan;
                    ctx.beginPath(); ctx.arc(cx0 + p.x * chW, cy0 + p.y * chH, r, 0, Math.PI * 2); ctx.fill();
                });
            } else {
                // gas: free flight + wall bounces; collision flashes on walls
                parts.forEach((p) => {
                    p.x += p.vx * 0.012 * speed; p.y += p.vy * 0.012 * speed;
                    if (p.x < 0.02 || p.x > 0.98) { p.vx *= -1; p.x = Math.max(0.02, Math.min(0.98, p.x)); }
                    if (p.y < 0.02 || p.y > 0.98) { p.vy *= -1; p.y = Math.max(0.02, Math.min(0.98, p.y)); }
                    ctx.fillStyle = acc;
                    ctx.beginPath(); ctx.arc(cx0 + p.x * chW, cy0 + p.y * chH, r - 0.7, 0, Math.PI * 2); ctx.fill();
                });
            }
            ctx.fillStyle = faint; ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'center';
            const caption = S.stateKey === 'solid'
                ? (TKn <= 1 ? 'absolute zero: least kinetic energy - the vibration has nothing left to give' : 'fixed positions, vibrating in place - strong forces, touching')
                : S.stateKey === 'liquid'
                    ? 'touching but free to slide past each other'
                    : 'far apart, flying freely, colliding with the walls';
            ctx.fillText(caption, w * 0.5, cy0 + chH + 20);
            // temperature strip (below the caption; top row belongs to the badge)
            ctx.fillStyle = TKn <= 1 ? rose : amber; ctx.font = '700 11px "JetBrains Mono", monospace';
            ctx.fillText(`${S.tC.toFixed(0)} °C  (${TKn.toFixed(0)} K above absolute zero)`, w * 0.5, cy0 + chH + 40);

            // ── Pressure gauge + p-V graph (right, gas only) ──
            const gx2 = w * 0.72;
            if (isGas) {
                const p = (TKn / 293) / S.vol;
                // gauge
                const gy2 = h * 0.26, gr = Math.min(w * 0.11, h * 0.16);
                ctx.strokeStyle = line; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.arc(gx2, gy2, gr, Math.PI * 0.85, Math.PI * 2.15); ctx.stroke();
                const frac = Math.min(1, p / 3);
                const ang = Math.PI * 0.85 + frac * Math.PI * 1.3 + Math.sin(t * 17) * 0.012 * speed;
                ctx.strokeStyle = amber; ctx.lineWidth = 3;
                ctx.beginPath(); ctx.moveTo(gx2, gy2);
                ctx.lineTo(gx2 + Math.cos(ang) * gr * 0.85, gy2 + Math.sin(ang) * gr * 0.85); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('pressure (collisions per second on the walls)', gx2, gy2 + gr + 14);
                ctx.fillStyle = acc; ctx.font = '700 11px "JetBrains Mono", monospace';
                ctx.fillText(`p = ${p.toFixed(2)} units`, gx2, gy2 + gr + 30);
                // p-V isotherm graph
                const px0 = gx2 - w * 0.11, py0 = h * 0.60, pw = w * 0.22, ph2 = h * 0.28;
                ctx.strokeStyle = ink; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.moveTo(px0, py0); ctx.lineTo(px0, py0 + ph2); ctx.lineTo(px0 + pw, py0 + ph2); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 8.5px Inter, sans-serif';
                ctx.fillText('volume →', px0 + pw / 2, py0 + ph2 + 12);
                ctx.save(); ctx.translate(px0 - 8, py0 + ph2 / 2); ctx.rotate(-Math.PI / 2);
                ctx.fillText('pressure →', 0, 0); ctx.restore();
                const k = TKn / 293;
                ctx.strokeStyle = 'rgba(52,211,153,.5)'; ctx.lineWidth = 2; ctx.beginPath();
                for (let v = 0.35; v <= 1.05; v += 0.02) {
                    const gxp = px0 + ((v - 0.35) / 0.7) * pw;
                    const gyp = py0 + ph2 - Math.min(1, (k / v) / 3) * ph2;
                    v <= 0.36 ? ctx.moveTo(gxp, gyp) : ctx.lineTo(gxp, gyp);
                }
                ctx.stroke();
                const dotX = px0 + ((S.vol - 0.35) / 0.7) * pw;
                const dotY = py0 + ph2 - Math.min(1, p / 3) * ph2;
                ctx.fillStyle = amber;
                ctx.beginPath(); ctx.arc(dotX, dotY, 5, 0, Math.PI * 2); ctx.fill();
                ctx.fillStyle = amber; ctx.font = '700 10px "JetBrains Mono", monospace';
                ctx.fillText(`p × V = ${(p * S.vol).toFixed(2)} (constant at this temperature)`, px0 + pw / 2, py0 - 8);
            } else {
                ctx.fillStyle = faint; ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('gauge and p–V graph live in the GAS state', gx2, h * 0.5);
            }

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Particles · motion · pressure</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label={stateKey === 'gas' ? 'gas pressure (arbitrary units)' : `particle structure: ${stateKey}`}
                      value={stateKey === 'gas' ? pGas.toFixed(2) : stateKey.toUpperCase()} tone={tC <= -272 ? 'warn' : 'acc'}
                      sub={tC <= -272
                          ? <>at <b>−273 °C</b> — absolute zero — the particles have their <b>least kinetic energy</b>; no lower temperature exists</>
                          : stateKey === 'gas'
                              ? <>p × V = <b>{(pGas * vol).toFixed(2)}</b> holds while the temperature stays fixed — squeeze and check</>
                              : <>watch the motion speed up as the temperature rises — temperature IS a story about particle motion</>} />
                <div className="cw-btnrow">
                    {STATES.map((s) => (
                        <button key={s} className={'cw-btn ' + (s === stateKey ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setStateKey(s)}>
                            {s}
                        </button>
                    ))}
                </div>
                <Slider label="temperature" value={tC} min={-273} max={400} step={1} onChange={setTC} format={(x) => `${x.toFixed(0)} °C`} />
                {stateKey === 'gas' && (
                    <Slider label="volume (push the piston)" value={vol} min={0.4} max={1.0} step={0.05} onChange={setVol} format={(x) => `${(x * 100).toFixed(0)}%`} />
                )}
                <Flag kind="neutral">
                    Slide the temperature to <b>−273 °C</b> in any state and watch the motion die away — absolute zero, the
                    floor of temperature. In the gas, watch the gauge: <b>hotter at fixed volume</b> means faster, harder,
                    more frequent wall collisions (pressure up); <b>squeezed at fixed temperature</b> means the same particles
                    hit the walls more often (pressure up) — and the amber p × V readout never moves: p₁V₁ = p₂V₂.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, state: stateKey, tC, vol })}>📌 Save this chamber to my notes</button>
                )}
            </div>
        </div>
    );
}
