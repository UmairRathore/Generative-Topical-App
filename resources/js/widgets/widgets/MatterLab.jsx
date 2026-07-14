import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag, Button } from '../primitives.jsx';

// ── Widget: matter_lab ───────────────────────────────────────────────────────
// Bespoke States of Matter hero simulator (5054 · 2.1.1). MACROSCOPIC on
// purpose (the particle story belongs to 2.1.2): a substance in a sealed
// container is heated and cooled through solid → liquid → gas, the transition
// names appear on the only four legal arrows (melting, boiling, condensing,
// freezing - the gas↔solid routes are excluded by the LO), a tilt test shows
// who keeps their shape, and a piston squeeze test shows who compresses.
//
// config: { state } · Notes contract: getState/setState carry {state, tilt}.

const STATES = ['solid', 'liquid', 'gas'];
const PROPS = {
    solid: { shape: 'definite shape', volume: 'definite volume', squeeze: 'not compressible', flow: 'does not flow' },
    liquid: { shape: 'takes the container\'s shape', volume: 'definite volume', squeeze: 'not compressible', flow: 'flows; surface stays level' },
    gas: { shape: 'no shape of its own', volume: 'fills the whole container', squeeze: 'compressible', flow: 'flows in all directions' },
};

export default function MatterLab({ config = {}, onReady, onAddToNote }) {
    const [stateIdx, setStateIdx] = useState(Math.max(0, STATES.indexOf(config.state ?? 'solid')));
    const [tilt, setTilt] = useState(false);
    const [squeeze, setSqueeze] = useState(false);
    const [transition, setTransition] = useState(null);     // label while morphing
    const transitionRef = useRef(null);
    transitionRef.current = transition;
    const cvRef = useRef(null);
    const animRef = useRef({ m: 0, tiltA: 0, pist: 0 });     // m: 0 solid, 1 liquid, 2 gas (eased)
    const st = useRef({}); st.current = { stateIdx, tilt, squeeze };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, state: STATES[st.current.stateIdx], tilt: st.current.tilt }),
            setState: (s) => {
                if (s?.state && STATES.includes(s.state)) setStateIdx(STATES.indexOf(s.state));
                if (typeof s?.tilt === 'boolean') setTilt(s.tilt);
            },
        });
    }, [onReady]); // eslint-disable-line

    const step = (dir) => {
        const next = stateIdx + dir;
        if (next < 0 || next > 2) return;
        const label = dir > 0 ? (stateIdx === 0 ? 'melting' : 'boiling') : (stateIdx === 2 ? 'condensing' : 'freezing');
        setTransition(label);
        setStateIdx(next);
        setSqueeze(false);
    };

    useEffect(() => {
        if (!transition) return undefined;
        const id = setTimeout(() => setTransition(null), 1600);
        return () => clearTimeout(id);
    }, [transition]);

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
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
            const cyan = '#38BDF8', amber = '#FBBF24';
            const S = st.current, a = animRef.current;
            a.m += (S.stateIdx - a.m) * 0.06;
            a.tiltA += ((S.tilt ? 0.16 : 0) - a.tiltA) * 0.1;
            a.pist += ((S.squeeze ? 1 : 0) - a.pist) * 0.08;

            // ── Container (left ~55%) ──
            const cx = w * 0.30, cyc = h * 0.52, cw2 = w * 0.19, ch2 = h * 0.30;
            ctx.save();
            ctx.translate(cx, cyc);
            ctx.rotate(a.tiltA);
            // piston squeeze shrinks the container's ceiling for the gas test
            const gasFrac = Math.max(0, a.m - 1);                         // 0..1 as liquid->gas
            const pistDrop = a.pist * ch2 * 0.5 * gasFrac;
            ctx.strokeStyle = ink; ctx.lineWidth = 2.5;
            ctx.strokeRect(-cw2, -ch2 + pistDrop, cw2 * 2, ch2 * 2 - pistDrop);
            if (a.pist > 0.03 && gasFrac > 0.5) {
                ctx.fillStyle = 'rgba(160,200,175,.25)';
                ctx.fillRect(-cw2, -ch2 + pistDrop - 8, cw2 * 2, 8);
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('piston', 0, -ch2 + pistDrop - 14);
            }
            // substance
            const solidness = Math.max(0, 1 - a.m);                        // 1 solid .. 0
            const liquidness = 1 - Math.abs(a.m - 1);                      // peak at liquid
            if (solidness > 0.05) {
                // solid block: keeps its own form, resting on the container floor, shrinking as it melts
                const bw = cw2 * 0.9, bh = ch2 * 0.7 * solidness;
                ctx.fillStyle = `rgba(56,189,248,${0.35 * solidness + 0.1})`;
                ctx.strokeStyle = cyan; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.roundRect(-bw / 2, ch2 - bh, bw, bh, 6);
                ctx.fill(); ctx.stroke();
            }
            if (liquidness > 0.05) {
                // liquid pool: surface stays LEVEL in world frame -> counter-rotate the surface
                ctx.save(); ctx.rotate(-a.tiltA);
                const lh = ch2 * 0.55 * liquidness;
                ctx.fillStyle = `rgba(56,189,248,${0.3 * liquidness})`;
                ctx.fillRect(-cw2 * 1.25, ch2 * 0.75 - lh, cw2 * 2.5, lh + ch2 * 0.35);
                ctx.strokeStyle = cyan; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.moveTo(-cw2 * 1.25, ch2 * 0.75 - lh); ctx.lineTo(cw2 * 1.25, ch2 * 0.75 - lh); ctx.stroke();
                ctx.restore();
            }
            if (gasFrac > 0.05) {
                // gas: faint haze filling everything below the piston
                ctx.fillStyle = `rgba(52,211,153,${0.12 * gasFrac})`;
                ctx.fillRect(-cw2 + 2, -ch2 + pistDrop + 2, cw2 * 2 - 4, ch2 * 2 - pistDrop - 4);
                for (let i = 0; i < 8; i++) {
                    const gx2 = -cw2 + 10 + ((t * 30 + i * 53) % (cw2 * 2 - 20));
                    const gy2 = -ch2 + pistDrop + 10 + ((t * 22 + i * 37) % (ch2 * 2 - pistDrop - 20));
                    ctx.fillStyle = `rgba(52,211,153,${0.4 * gasFrac})`;
                    ctx.beginPath(); ctx.arc(gx2, gy2, 2.2, 0, Math.PI * 2); ctx.fill();
                }
            }
            ctx.restore();
            ctx.fillStyle = faint; ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText(S.tilt ? 'tilted: the solid sits, the liquid re-levels, the gas never noticed' : 'a sealed container of one substance', cx, cyc + ch2 + 22);
            if (transitionRef.current) {
                ctx.fillStyle = amber; ctx.font = '700 13px Inter, sans-serif';
                ctx.fillText(transitionRef.current + '…', cx, cyc - ch2 - 14);
            }

            // ── Legal state-change map (right) ──
            const mx = w * 0.72, my = h * 0.24, gap = h * 0.24;
            const nodes = [['SOLID', my], ['LIQUID', my + gap], ['GAS', my + gap * 2]];
            nodes.forEach(([lbl, y], i) => {
                const active = Math.round(a.m) === i;
                ctx.fillStyle = active ? 'rgba(52,211,153,.18)' : 'rgba(160,200,175,.06)';
                ctx.strokeStyle = active ? acc : line; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.roundRect(mx - 52, y - 15, 104, 30, 8); ctx.fill(); ctx.stroke();
                ctx.fillStyle = active ? acc : faint; ctx.font = '700 11px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(lbl, mx, y + 4);
            });
            const arrow = (y0, y1, xoff, label) => {
                ctx.strokeStyle = faint; ctx.fillStyle = faint; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.moveTo(mx + xoff, y0); ctx.lineTo(mx + xoff, y1); ctx.stroke();
                const s2 = Math.sign(y1 - y0);
                ctx.beginPath(); ctx.moveTo(mx + xoff, y1 + s2 * 6);
                ctx.lineTo(mx + xoff - 4, y1); ctx.lineTo(mx + xoff + 4, y1); ctx.fill();
                ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = xoff < 0 ? 'right' : 'left';
                ctx.fillText(label, mx + xoff + (xoff < 0 ? -8 : 8), (y0 + y1) / 2 + 3);
            };
            arrow(nodes[0][1] + 18, nodes[1][1] - 18, 22, 'melting');
            arrow(nodes[1][1] + 18, nodes[2][1] - 18, 22, 'boiling');
            arrow(nodes[2][1] - 18, nodes[1][1] + 18, -22, 'condensing');
            arrow(nodes[1][1] - 18, nodes[0][1] + 18, -22, 'freezing');
            ctx.fillStyle = faint; ctx.font = '600 8.5px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText('the only four transfers this course names', mx, nodes[2][1] + 32);

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const stateKey = STATES[stateIdx];
    const P = PROPS[stateKey];

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Three states · four names</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label={transition ? `changing state: ${transition}` : `current state: ${stateKey}`}
                      value={stateKey.toUpperCase()} tone="acc"
                      sub={<><b>{P.shape}</b> · {P.volume} · {P.squeeze} · {P.flow}</>} />
                <div className="cw-btnrow">
                    <Button variant="save" onClick={() => step(1)}>🔥 Heat</Button>
                    <Button variant="ghost" onClick={() => step(-1)}>❄ Cool</Button>
                </div>
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (tilt ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setTilt(!tilt)}>⟲ Tilt the container</button>
                    <button className={'cw-btn ' + (squeeze ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setSqueeze(!squeeze)} disabled={stateKey !== 'gas'}>
                        ▣ Squeeze (piston)
                    </button>
                </div>
                <Flag kind="neutral">
                    Heat and cool your way around the map and read the arrow names as you cross — <b>melting, boiling,
                    condensing, freezing</b>: the only four this course asks for (solid↔gas routes are not required).
                    The tilt test sorts shape; the piston sorts compressibility — only the <b>gas</b> gives way.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, state: stateKey, tilt })}>📌 Save this state to my notes</button>
                )}
            </div>
        </div>
    );
}
