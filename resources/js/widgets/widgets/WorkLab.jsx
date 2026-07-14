import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag, Button } from '../primitives.jsx';

// ── Widget: work_lab ─────────────────────────────────────────────────────────
// Bespoke Work hero simulator (5054 · 1.7.2). A crate pushed across a floor:
// the work done accumulates LIVE as the shaded area on a force–distance graph
// (W = Fd made geometric), with the equation's numbers in place. A second
// scene carries the crate at steady height: the supporting force is
// perpendicular to the motion, the distance moved IN THE DIRECTION OF THE
// FORCE is zero - and the work readout stays at zero however far you walk.
// Scenario values are illustrative.
//
// config: { scene, force } · Notes contract: getState/setState carry
// {scene, force, d}.

const D_MAX = 8;      // metres of travel shown

export default function WorkLab({ config = {}, onReady, onAddToNote }) {
    const [scene, setScene] = useState(config.scene === 'carry' ? 'carry' : 'push');
    const [force, setForce] = useState(typeof config.force === 'number' ? config.force : 40);
    const [running, setRunning] = useState(false);
    const [dShown, setDShown] = useState(0);
    const simRef = useRef({ d: 0 });
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { scene, force, running };

    const reset = () => { simRef.current.d = 0; setDShown(0); setRunning(false); };
    useEffect(reset, [scene, force]); // eslint-disable-line

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, scene: st.current.scene, force: st.current.force, d: simRef.current.d }),
            setState: (s) => {
                if (s?.scene === 'push' || s?.scene === 'carry') setScene(s.scene);
                if (typeof s?.force === 'number') setForce(Math.max(10, Math.min(100, s.force)));
                if (typeof s?.d === 'number') simRef.current.d = Math.max(0, Math.min(D_MAX, s.d));
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
            const S = st.current, sim = simRef.current;

            if (S.running) {
                sim.d = Math.min(D_MAX, sim.d + dt * 1.6);
                setDShown(sim.d);
                if (sim.d >= D_MAX) setRunning(false);
            }
            const isPush = S.scene === 'push';
            const workDone = isPush ? S.force * sim.d : 0;

            // ── Scene (top ~52%) ──
            const floorY = h * 0.44, tx = 24, tw = w - 48;
            const X = (m) => tx + (m / D_MAX) * (tw * 0.8);
            ctx.strokeStyle = ink; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(tx, floorY); ctx.lineTo(tx + tw, floorY); ctx.stroke();
            ctx.font = '600 8px "JetBrains Mono", monospace'; ctx.fillStyle = faint; ctx.textAlign = 'center';
            for (let m = 0; m <= D_MAX; m += 2) {
                ctx.strokeStyle = line; ctx.beginPath(); ctx.moveTo(X(m), floorY); ctx.lineTo(X(m), floorY + 6); ctx.stroke();
                ctx.fillText(`${m} m`, X(m), floorY + 17);
            }
            const cx2 = X(sim.d), lift = isPush ? 0 : 26;
            // crate
            ctx.fillStyle = 'rgba(56,189,248,.14)'; ctx.strokeStyle = cyan; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.roundRect(cx2 - 20, floorY - 40 - lift, 40, 40, 5); ctx.fill(); ctx.stroke();
            // person hint
            ctx.strokeStyle = faint; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.arc(cx2 - 34, floorY - 52 - lift, 6, 0, Math.PI * 2); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(cx2 - 34, floorY - 46 - lift); ctx.lineTo(cx2 - 34, floorY - 22 - lift); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(cx2 - 34, floorY - 40 - lift); ctx.lineTo(cx2 - 20, floorY - 34 - lift); ctx.stroke();
            const arrow = (x0, y0, dx, dy, col, label) => {
                ctx.strokeStyle = col; ctx.fillStyle = col; ctx.lineWidth = 2.5;
                ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x0 + dx, y0 + dy); ctx.stroke();
                const len = Math.hypot(dx, dy) || 1, ux = dx / len, uy = dy / len;
                ctx.beginPath(); ctx.moveTo(x0 + dx + ux * 8, y0 + dy + uy * 8);
                ctx.lineTo(x0 + dx - uy * 4, y0 + dy + ux * 4); ctx.lineTo(x0 + dx + uy * 4, y0 + dy - ux * 4); ctx.fill();
                ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(label, x0 + dx / 2 + uy * 16, y0 + dy / 2 - ux * 16 + 3);
            };
            if (isPush) {
                arrow(cx2 - 20, floorY - 20, -(24 + S.force * 0.5), 0, acc, `push ${S.force} N`);
                // motion arrow
                arrow(cx2 + 24, floorY - 58, 34, 0, amber, 'motion');
            } else {
                arrow(cx2, floorY - 40 - lift, 0, -(24 + S.force * 0.5), acc, `support ${S.force} N`);
                arrow(cx2 + 24, floorY - 58 - lift, 34, 0, amber, 'motion');
                ctx.fillStyle = faint; ctx.font = '600 9.5px Inter, sans-serif';
                ctx.fillText('force ⊥ motion: distance moved in the direction of the force = 0', w / 2, floorY + 34);
            }

            // ── F–d graph (bottom): W = the shaded area ──
            const gx = w * 0.12, gy = h * 0.56, gw = w * 0.56, gh = h * 0.34;
            const GX = (m) => gx + (m / D_MAX) * gw;
            const GY = (F) => gy + gh - (F / 100) * gh;
            ctx.strokeStyle = ink; ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.moveTo(gx, gy); ctx.lineTo(gx, gy + gh); ctx.lineTo(gx + gw, gy + gh); ctx.stroke();
            ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText('distance moved in the direction of the force / m →', gx + gw / 2, gy + gh + 14);
            ctx.save(); ctx.translate(gx - 26, gy + gh / 2); ctx.rotate(-Math.PI / 2);
            ctx.fillText('force / N →', 0, 0); ctx.restore();
            ctx.font = '600 8px "JetBrains Mono", monospace'; ctx.textAlign = 'right';
            for (let F = 0; F <= 100; F += 25) ctx.fillText(String(F), gx - 4, GY(F) + 2.5);
            ctx.textAlign = 'center';
            for (let m = 0; m <= D_MAX; m += 2) ctx.fillText(String(m), GX(m), gy + gh + 6);
            const dEff = isPush ? sim.d : 0;
            if (dEff > 0.02) {
                const grad = ctx.createLinearGradient(0, GY(S.force), 0, gy + gh);
                grad.addColorStop(0, 'rgba(52,211,153,.5)'); grad.addColorStop(1, 'rgba(52,211,153,.15)');
                ctx.fillStyle = grad;
                ctx.fillRect(gx, GY(S.force), GX(dEff) - gx, gy + gh - GY(S.force));
                ctx.fillStyle = acc; ctx.font = '700 11px "JetBrains Mono", monospace';
                ctx.fillText(`area = W = ${Math.round(S.force * dEff)} J`, (gx + GX(dEff)) / 2, (GY(S.force) + gy + gh) / 2 + 4);
            }
            ctx.strokeStyle = acc; ctx.lineWidth = 2; ctx.setLineDash(isPush ? [] : [4, 4]);
            ctx.beginPath(); ctx.moveTo(gx, GY(S.force)); ctx.lineTo(GX(isPush ? D_MAX : 0.001), GY(S.force)); ctx.stroke(); ctx.setLineDash([]);

            // live equation (right of graph)
            const px2 = w * 0.85;
            ctx.font = '700 11.5px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            ctx.fillStyle = faint; ctx.fillText('W = F × d', px2, gy + 18);
            ctx.fillStyle = amber;
            ctx.fillText(isPush ? `= ${S.force} × ${sim.d.toFixed(1)}` : `= ${S.force} × 0`, px2, gy + 38);
            ctx.fillStyle = acc;
            ctx.fillText(`= ${Math.round(workDone)} J`, px2, gy + 58);
            if (!isPush) {
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif';
                ctx.fillText('(d in the force’s direction)', px2, gy + 76);
            }

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const d = dShown;

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Work = force × distance = the area</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label={scene === 'push' ? 'work done by the push' : 'work done by the supporting force'}
                      value={scene === 'push' ? Math.round(force * d) : 0} unit="J" tone={scene === 'push' ? 'acc' : 'warn'}
                      sub={scene === 'push'
                          ? <>the shaded rectangle grows as the crate moves — <b>W = Fd</b> is an area</>
                          : <>the crate moves, but <b>not in the force's direction</b> — the support force does no work however far you carry it</>} />
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (scene === 'push' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setScene('push')}>→ Push along the floor</button>
                    <button className={'cw-btn ' + (scene === 'carry' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setScene('carry')}>⇧ Carry at steady height</button>
                </div>
                <Slider label={scene === 'push' ? 'pushing force' : 'supporting force'} value={force} min={10} max={100} step={5} onChange={setForce} format={(x) => `${x.toFixed(0)} N`} />
                <div className="cw-btnrow">
                    <Button variant="save" onClick={() => setRunning(!running)}>{running ? '⏸ Pause' : '▶ Move the crate'}</Button>
                    <Button variant="ghost" onClick={reset}>↺ Reset</Button>
                </div>
                <Flag kind="neutral">
                    Read the axis label carefully: <b>distance moved in the direction of the force</b>. Push, and every metre
                    counts — the work is the growing area. Carry, and the support force points up while the motion is sideways:
                    that distance is <b>zero</b>, so W = F × 0 = 0, no matter how far you walk.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, scene, force, d })}>📌 Save this job to my notes</button>
                )}
            </div>
        </div>
    );
}
