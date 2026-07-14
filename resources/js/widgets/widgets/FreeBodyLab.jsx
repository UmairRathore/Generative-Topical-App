import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag, Button } from '../primitives.jsx';

// ── Widget: freebody_lab ─────────────────────────────────────────────────────
// Bespoke Forces & Newton's First Law hero simulator (5054 · 1.5.1 LOs 1-5).
// A free-body diagram BUILDER: pick a scene, toggle the forces you believe act,
// and the diagram draws itself. When the diagram is right, the magnitudes
// appear, the same-line resultant is computed, and Newton's first law delivers
// the verdict on the motion. Scene magnitudes are illustrative scenario data.
//
// config: { scene } · Notes contract: getState/setState carry {scene, chosen}.

const FORCE_TYPES = [
    { key: 'weight', label: 'weight' },
    { key: 'normal', label: 'normal contact' },
    { key: 'friction', label: 'friction' },
    { key: 'air', label: 'air resistance' },
    { key: 'tension', label: 'tension' },
    { key: 'push', label: 'applied pull/push' },
];

// dir: [dx, dy] unit; N: magnitude; each scene lists exactly the forces that act.
const SCENES = [
    {
        key: 'book', label: 'Book resting on a table', emoji: '📕',
        state: 'at rest', verdictBalanced: 'stays at rest',
        forces: { weight: { dir: [0, 1], N: 4 }, normal: { dir: [0, -1], N: 4 } },
    },
    {
        key: 'skydiver', label: 'Skydiver still speeding up', emoji: '🪂',
        state: 'moving down, speeding up', verdictBalanced: null,
        forces: { weight: { dir: [0, 1], N: 800 }, air: { dir: [0, -1], N: 300 } },
    },
    {
        key: 'crate', label: 'Crate pulled at steady speed', emoji: '📦',
        state: 'moving right at constant speed', verdictBalanced: 'keeps moving at constant velocity',
        forces: {
            weight: { dir: [0, 1], N: 100 }, normal: { dir: [0, -1], N: 100 },
            push: { dir: [1, 0], N: 50 }, friction: { dir: [-1, 0], N: 50 },
        },
    },
    {
        key: 'lamp', label: 'Lamp hanging from a cable', emoji: '💡',
        state: 'at rest', verdictBalanced: 'stays at rest',
        forces: { weight: { dir: [0, 1], N: 6 }, tension: { dir: [0, -1], N: 6 } },
    },
];

export default function FreeBodyLab({ config = {}, onReady, onAddToNote }) {
    const [sceneKey, setSceneKey] = useState(SCENES.some((s) => s.key === config.scene) ? config.scene : 'book');
    const [chosen, setChosen] = useState([]);
    const cvRef = useRef(null);
    const pulseRef = useRef(0);
    const st = useRef({}); st.current = { sceneKey, chosen };

    const scene = SCENES.find((s) => s.key === sceneKey);
    const actual = Object.keys(scene.forces);
    const missing = actual.filter((k) => !chosen.includes(k));
    const extra = chosen.filter((k) => !actual.includes(k));
    const complete = missing.length === 0 && extra.length === 0;
    // same-line resultants (x right +, y down +)
    const res = actual.reduce((acc, k) => {
        const f = scene.forces[k];
        return { x: acc.x + f.dir[0] * f.N, y: acc.y + f.dir[1] * f.N };
    }, { x: 0, y: 0 });
    const balanced = Math.abs(res.x) < 1e-9 && Math.abs(res.y) < 1e-9;

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, scene: st.current.sceneKey, chosen: st.current.chosen }),
            setState: (s) => {
                if (s?.scene && SCENES.some((x) => x.key === s.scene)) setSceneKey(s.scene);
                if (Array.isArray(s?.chosen)) setChosen(s.chosen.filter((k) => FORCE_TYPES.some((f) => f.key === k)));
            },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf;
        const draw = () => {
            pulseRef.current += 0.04;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return;
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const acc = cssVar('--ok', '#34D399');
            const cyan = '#38BDF8', amber = '#FBBF24', rose = '#FB7185';
            const { sceneKey: sk, chosen: ch } = st.current;
            const S = SCENES.find((x) => x.key === sk);
            const done = Object.keys(S.forces).every((k) => ch.includes(k)) && ch.every((k) => S.forces[k]);

            const cx = w * 0.5, cy = h * 0.52;
            // scene furniture
            ctx.strokeStyle = faint; ctx.lineWidth = 2;
            if (sk === 'book' || sk === 'crate') {                       // surface below
                ctx.beginPath(); ctx.moveTo(cx - w * 0.3, cy + 34); ctx.lineTo(cx + w * 0.3, cy + 34); ctx.stroke();
                for (let i = 0; i < 10; i++) {
                    ctx.beginPath(); ctx.moveTo(cx - w * 0.3 + i * (w * 0.06), cy + 34);
                    ctx.lineTo(cx - w * 0.3 + i * (w * 0.06) - 8, cy + 44); ctx.stroke();
                }
            }
            if (sk === 'lamp') {                                         // ceiling + cable
                ctx.beginPath(); ctx.moveTo(cx - w * 0.2, cy - 118); ctx.lineTo(cx + w * 0.2, cy - 118); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(cx, cy - 118); ctx.lineTo(cx, cy - 26); ctx.stroke();
            }
            if (sk === 'skydiver') {                                     // motion streaks
                for (let i = 0; i < 4; i++) {
                    const yy = ((pulseRef.current * 40 + i * 60) % (h * 0.8));
                    ctx.strokeStyle = 'rgba(160,200,175,.10)';
                    ctx.beginPath(); ctx.moveTo(cx - 90 + i * 60, yy); ctx.lineTo(cx - 90 + i * 60, yy - 22); ctx.stroke();
                }
            }
            // the object (a plain box — free-body style — with an emoji tag)
            ctx.fillStyle = 'rgba(56,189,248,.14)';
            ctx.strokeStyle = cyan; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.roundRect(cx - 26, cy - 26, 52, 52, 8); ctx.fill(); ctx.stroke();
            ctx.font = '22px serif'; ctx.textAlign = 'center'; ctx.fillText(S.emoji, cx, cy + 8);
            ctx.font = '600 10px Inter, sans-serif'; ctx.fillStyle = faint;
            ctx.fillText(S.state, cx, cy + 62);

            // chosen force arrows
            const maxN = Math.max(...Object.values(S.forces).map((f) => f.N));
            ch.forEach((k) => {
                const real = S.forces[k];
                const ft = FORCE_TYPES.find((f) => f.key === k);
                if (real) {
                    const L = 34 + (real.N / maxN) * 56;
                    const [dx, dy] = real.dir;
                    const x0 = cx + dx * 28, y0 = cy + dy * 28;
                    const x1 = cx + dx * (28 + L), y1 = cy + dy * (28 + L);
                    ctx.strokeStyle = acc; ctx.fillStyle = acc; ctx.lineWidth = 2.5;
                    ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke();
                    ctx.beginPath();
                    ctx.moveTo(x1 + dx * 9, y1 + dy * 9);
                    ctx.lineTo(x1 - dy * 4.5, y1 - dx * 4.5);
                    ctx.lineTo(x1 + dy * 4.5, y1 + dx * 4.5);
                    ctx.fill();
                    ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                    ctx.fillText(ft.label + (done ? ` ${real.N} N` : ''), x1 + dx * 30 + (dy !== 0 ? 0 : 0), y1 + dy * 24 + (dx !== 0 ? -10 : 3));
                } else {
                    // a force that is NOT acting here: dashed rose ghost arrow off the corner
                    const ang = -Math.PI / 4;
                    ctx.setLineDash([4, 4]); ctx.strokeStyle = rose; ctx.lineWidth = 2;
                    ctx.beginPath(); ctx.moveTo(cx + 30, cy - 30);
                    ctx.lineTo(cx + 30 + Math.cos(ang) * 40, cy - 30 + Math.sin(ang) * 40); ctx.stroke();
                    ctx.setLineDash([]);
                    ctx.fillStyle = rose; ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'left';
                    ctx.fillText(`${ft.label}? nothing provides it here`, cx + 40, cy - 66);
                }
            });

            // resultant strip once complete
            if (done) {
                const rx = Object.keys(S.forces).reduce((a, k) => a + S.forces[k].dir[0] * S.forces[k].N, 0);
                const ry = Object.keys(S.forces).reduce((a, k) => a + S.forces[k].dir[1] * S.forces[k].N, 0);
                ctx.font = '700 11.5px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                if (Math.abs(rx) < 1e-9 && Math.abs(ry) < 1e-9) {
                    ctx.fillStyle = acc;
                    ctx.fillText('resultant = 0 — forces balanced', cx, h - 10);
                } else {
                    const mag = Math.abs(rx) > 0 ? Math.abs(rx) : Math.abs(ry);
                    const dirTxt = Math.abs(rx) > 0 ? (rx > 0 ? 'to the right' : 'to the left') : (ry > 0 ? 'downward' : 'upward');
                    ctx.fillStyle = amber;
                    ctx.fillText(`resultant = ${mag} N ${dirTxt} — velocity will change`, cx, h - 10);
                    const glow = 0.5 + 0.5 * Math.sin(pulseRef.current * 2);
                    ctx.globalAlpha = 0.5 + glow * 0.5;
                    const dx = Math.sign(rx), dy = Math.sign(ry);
                    ctx.strokeStyle = amber; ctx.fillStyle = amber; ctx.lineWidth = 3.5;
                    const x0 = cx + dx * 28, y0 = cy + dy * 28;
                    const x1 = cx + dx * 120, y1 = cy + dy * 120;
                    ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke();
                    ctx.globalAlpha = 1;
                }
            }

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const toggle = (k) => setChosen((c) => (c.includes(k) ? c.filter((x) => x !== k) : [...c, k]));
    const verdict = complete
        ? (balanced ? `Balanced — Newton's first law: it ${scene.verdictBalanced}.` : 'Unbalanced — a resultant force acts, so the velocity changes.')
        : `${actual.length - missing.length}/${actual.length} real forces placed` + (extra.length ? ` · ${extra.length} that don't belong` : '');

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Build the free-body diagram</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label={complete ? (balanced ? 'diagram complete — balanced' : 'diagram complete — unbalanced') : 'diagram under construction'}
                      value={complete ? (balanced ? 'resultant 0' : 'resultant ≠ 0') : `${chosen.length} arrow${chosen.length === 1 ? '' : 's'}`}
                      tone={complete ? (balanced ? 'acc' : 'warn') : undefined}
                      sub={verdict} />
                <div className="cw-btnrow">
                    {SCENES.map((s) => (
                        <button key={s.key} className={'cw-btn ' + (s.key === sceneKey ? 'cw-btn-save' : 'cw-btn-ghost')}
                                onClick={() => { setSceneKey(s.key); setChosen([]); }}>
                            {s.emoji} {s.label}
                        </button>
                    ))}
                </div>
                <div className="cw-btnrow">
                    {FORCE_TYPES.map((f) => (
                        <button key={f.key} className={'cw-btn ' + (chosen.includes(f.key) ? 'cw-btn-save' : 'cw-btn-ghost')}
                                onClick={() => toggle(f.key)}>
                            {chosen.includes(f.key) ? '✓ ' : '+ '}{f.label}
                        </button>
                    ))}
                </div>
                <Flag kind="neutral">
                    A free-body diagram shows <b>one object</b> and <b>only the forces acting on it</b>. Toggle a force that
                    nothing provides and the diagram objects. Complete it correctly and the magnitudes appear — then read the
                    same-line resultant and let <b>Newton's first law</b> call the motion.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, scene: sceneKey, chosen })}>📌 Save this diagram to my notes</button>
                )}
            </div>
        </div>
    );
}
