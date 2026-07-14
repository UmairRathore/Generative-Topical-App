import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag, Button } from '../primitives.jsx';

// ── Widget: shc_lab ──────────────────────────────────────────────────────────
// Bespoke Specific Heat Capacity hero simulator (5054 · 2.2.2). The measuring
// experiment performed: an electric heater in a block (or beaker of liquid)
// with a joulemeter counting energy in and a thermometer reading the rise.
// Two samples run side by side on the SAME joulemeter so equal energies land
// in both - the low-c material's temperature races ahead - and the panel
// assembles c = ΔE/(mΔθ) live from the three measured numbers. An insulation
// toggle shows where the real experiment leaks. Values are standard data.
//
// config: { left, right } · Notes contract: getState/setState carry
// {left, right, mass, E}.

const MATERIALS = [
    { key: 'water', label: 'water (liquid)', c: 4200, col: '#38BDF8', beaker: true },
    { key: 'aluminium', label: 'aluminium block', c: 900, col: '#9aa5b1', beaker: false },
    { key: 'copper', label: 'copper block', c: 390, col: '#d97706', beaker: false },
];
const POWER = 50;          // W heater
const MASS = 1.0;          // kg each sample
const T0 = 20;             // start °C
const E_MAX = 42000;       // J cap (water +10 °C)

export default function ShcLab({ config = {}, onReady, onAddToNote }) {
    const [leftKey, setLeftKey] = useState(MATERIALS.some((m) => m.key === config.left) ? config.left : 'water');
    const [rightKey, setRightKey] = useState(MATERIALS.some((m) => m.key === config.right) ? config.right : 'copper');
    const [running, setRunning] = useState(false);
    const [insulated, setInsulated] = useState(true);
    const [snapE, setSnapE] = useState(0);
    const simRef = useRef({ E: 0 });
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { leftKey, rightKey, running, insulated };

    const reset = () => { simRef.current.E = 0; setSnapE(0); setRunning(false); };
    useEffect(reset, [leftKey, rightKey, insulated]); // eslint-disable-line

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, left: st.current.leftKey, right: st.current.rightKey, mass: MASS, E: simRef.current.E }),
            setState: (s) => {
                if (s?.left && MATERIALS.some((m) => m.key === s.left)) setLeftKey(s.left);
                if (s?.right && MATERIALS.some((m) => m.key === s.right)) setRightKey(s.right);
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
            const amber = '#FBBF24', rose = '#FB7185';
            const S = st.current, sim = simRef.current;
            const leak = S.insulated ? 1.0 : 0.75;                    // fraction of energy retained without insulation

            if (S.running) {
                sim.E = Math.min(E_MAX, sim.E + POWER * dt * 14);      // 14x time-lapse
                setSnapE(sim.E);
                if (sim.E >= E_MAX) setRunning(false);
            }

            const samples = [
                MATERIALS.find((m) => m.key === S.leftKey),
                MATERIALS.find((m) => m.key === S.rightKey),
            ];
            const xs = [w * 0.22, w * 0.56];
            ctx.font = '600 9.5px Inter, sans-serif';
            samples.forEach((mat, i) => {
                const x = xs[i], baseY = h * 0.62;
                const dT = (sim.E * leak) / (MASS * mat.c);
                const T = T0 + dT;
                // vessel
                ctx.strokeStyle = ink; ctx.lineWidth = 2;
                if (mat.beaker) {
                    ctx.beginPath(); ctx.moveTo(x - 34, baseY - 64); ctx.lineTo(x - 34, baseY); ctx.lineTo(x + 34, baseY); ctx.lineTo(x + 34, baseY - 64); ctx.stroke();
                    ctx.fillStyle = 'rgba(56,189,248,.25)';
                    ctx.fillRect(x - 32, baseY - 54, 64, 52);
                } else {
                    ctx.fillStyle = mat.col + '44';
                    ctx.strokeStyle = mat.col;
                    ctx.beginPath(); ctx.roundRect(x - 34, baseY - 58, 68, 58, 5); ctx.fill(); ctx.stroke();
                }
                // heater element inside
                ctx.strokeStyle = rose; ctx.lineWidth = 2.5;
                ctx.beginPath(); ctx.moveTo(x - 20, baseY - 12);
                for (let k = 0; k < 5; k++) ctx.lineTo(x - 20 + (k + 0.5) * 8, baseY - (k % 2 ? 8 : 18));
                ctx.lineTo(x + 20, baseY - 12); ctx.stroke();
                // insulation jacket
                if (S.insulated) {
                    ctx.strokeStyle = 'rgba(160,200,175,.4)'; ctx.setLineDash([5, 4]); ctx.lineWidth = 3;
                    ctx.strokeRect(x - 42, baseY - 70, 84, 74); ctx.setLineDash([]);
                } else {
                    // escaping heat wiggles
                    for (let k = 0; k < 3; k++) {
                        const p = ((now / 700 + k / 3) % 1);
                        ctx.strokeStyle = `rgba(251,113,133,${0.5 * (1 - p)})`; ctx.lineWidth = 2;
                        const yy = baseY - 70 - p * 18;
                        ctx.beginPath(); ctx.moveTo(x - 10 + k * 10, yy); ctx.quadraticCurveTo(x - 5 + k * 10, yy - 4, x + k * 10, yy); ctx.stroke();
                    }
                }
                // thermometer readout
                ctx.fillStyle = amber; ctx.font = '700 12px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(`${T.toFixed(1)} °C`, x, baseY - 78);
                ctx.fillStyle = faint; ctx.font = '600 9.5px Inter, sans-serif';
                ctx.fillText(`${mat.label} · ${MASS.toFixed(1)} kg`, x, baseY + 18);
                ctx.fillText(`Δθ = ${dT.toFixed(1)} °C`, x, baseY + 32);
                // live c computation once some energy is in
                if (sim.E > 500) {
                    const cCalc = (sim.E * leak) / (MASS * dT || 1);
                    ctx.fillStyle = dT > 0.2 ? acc : faint; ctx.font = '700 9.5px "JetBrains Mono", monospace';
                    ctx.fillText(`c = ${Math.round(sim.E)} / (${MASS.toFixed(1)} x ${dT.toFixed(1)})`, x, baseY + 50);
                    ctx.fillText(`= ${Math.round(cCalc)} J/(kg °C)${S.insulated ? '' : '  << too high!'}`, x, baseY + 64);
                }
            });
            // shared joulemeter
            ctx.fillStyle = 'rgba(0,0,0,.4)';
            ctx.beginPath(); ctx.roundRect(w * 0.82 - 52, h * 0.30, 104, 44, 6); ctx.fill();
            ctx.fillStyle = acc; ctx.font = '700 13px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            ctx.fillText(`${Math.round(simRef.current.E)}`, w * 0.82, h * 0.30 + 20);
            ctx.fillStyle = faint; ctx.font = '600 8.5px Inter, sans-serif';
            ctx.fillText('joulemeter: energy supplied to EACH', w * 0.82, h * 0.30 + 36);
            ctx.fillText(`heater power ${POWER} W (time-lapse)`, w * 0.82, h * 0.30 + 56);
            if (!S.insulated) {
                ctx.fillStyle = rose;
                ctx.fillText('no insulation: some energy escapes -', w * 0.82, h * 0.30 + 76);
                ctx.fillText('the computed c comes out TOO HIGH', w * 0.82, h * 0.30 + 90);
            }

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const leftMat = MATERIALS.find((m) => m.key === leftKey);
    const rightMat = MATERIALS.find((m) => m.key === rightKey);
    const leak = insulated ? 1.0 : 0.75;
    const dTL = (snapE * leak) / (MASS * leftMat.c), dTR = (snapE * leak) / (MASS * rightMat.c);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Same joules · different rises</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="the race so far (equal energy into each)"
                      value={`${(T0 + dTL).toFixed(1)} vs ${(T0 + dTR).toFixed(1)}`} unit="°C" tone="acc"
                      sub={<>the LOW-c sample races ahead — c = ΔE/(mΔθ) says the same joules buy a bigger Δθ when each degree costs less</>} />
                <div className="cw-btnrow">
                    {MATERIALS.map((m) => (
                        <button key={'L' + m.key} className={'cw-btn ' + (m.key === leftKey ? 'cw-btn-save' : 'cw-btn-ghost')}
                                onClick={() => setLeftKey(m.key)}>
                            L: {m.label.split(' ')[0]}
                        </button>
                    ))}
                </div>
                <div className="cw-btnrow">
                    {MATERIALS.map((m) => (
                        <button key={'R' + m.key} className={'cw-btn ' + (m.key === rightKey ? 'cw-btn-save' : 'cw-btn-ghost')}
                                onClick={() => setRightKey(m.key)}>
                            R: {m.label.split(' ')[0]}
                        </button>
                    ))}
                </div>
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (insulated ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setInsulated(!insulated)}>
                        {insulated ? '✓' : '+'} insulation
                    </button>
                    <Button variant="save" onClick={() => setRunning(!running)}>{running ? '⏸ Pause' : '▶ Heat both'}</Button>
                    <Button variant="ghost" onClick={reset}>↺ Reset</Button>
                </div>
                <Flag kind="neutral">
                    This IS the measuring experiment: a heater supplies a known ΔE (the joulemeter), a balance gave m, a
                    thermometer reads Δθ — and <b>c = ΔE/(mΔθ)</b> falls out. Switch the insulation off and watch the
                    computed c drift <b>too high</b>: escaped energy still got counted by the joulemeter but never warmed
                    the sample.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, left: leftKey, right: rightKey, E: snapE })}>📌 Save this run to my notes</button>
                )}
            </div>
        </div>
    );
}
