import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag, Button } from '../primitives.jsx';

// ── Widget: density_lab ──────────────────────────────────────────────────────
// Bespoke Density hero simulator (5054 · 1.4). The complete irregular-solid
// determination on a rendered lab bench: weigh the object on an electronic
// balance, lower it into a lit glass measuring cylinder and watch the water
// rise with a real meniscus, then assemble ρ = m / V live — the RISE bracketed
// so the final-level trap is defused visually. Object densities are scenario data.
//
// Lake-bar immersion: lit bench, glass sheen + meniscus + refraction, material
// texture on each object. Logic/contract unchanged from the schematic version.
//
// config: { object } · Notes contract: getState/setState carry {object, step}.

const OBJECTS = [
    { key: 'stone', label: 'Stone', m: 117, V: 45, col: '#9aa5b1' },
    { key: 'bolt', label: 'Steel bolt', m: 158, V: 20, col: '#8fb4d9' },
    { key: 'block', label: 'Aluminium block', m: 135, V: 50, col: '#c9d4de' },
];
const INITIAL = 50; // cm³ of water before submerging
const STEPS = ['pick', 'weighed', 'submerged', 'computed'];

// render an object with a bit of material character
function drawObject(ctx, x, y, r, obj, alpha = 1) {
    ctx.save(); ctx.globalAlpha = alpha;
    if (obj.key === 'stone') {
        ctx.fillStyle = obj.col;
        ctx.beginPath();
        ctx.moveTo(x - r, y); ctx.lineTo(x - r * 0.6, y - r * 0.8); ctx.lineTo(x + r * 0.3, y - r);
        ctx.lineTo(x + r, y - r * 0.3); ctx.lineTo(x + r * 0.7, y + r * 0.7); ctx.lineTo(x - r * 0.4, y + r * 0.8);
        ctx.closePath(); ctx.fill();
        ctx.fillStyle = 'rgba(0,0,0,.18)';
        [[-0.3, -0.2, 0.28], [0.3, 0.15, 0.22], [0.1, -0.45, 0.16]].forEach(([dx, dy, rr]) => {
            ctx.beginPath(); ctx.ellipse(x + dx * r, y + dy * r, rr * r, rr * r * 0.7, 0, 0, Math.PI * 2); ctx.fill();
        });
        ctx.fillStyle = 'rgba(255,255,255,.14)';
        ctx.beginPath(); ctx.ellipse(x - r * 0.35, y - r * 0.5, r * 0.3, r * 0.18, -0.5, 0, Math.PI * 2); ctx.fill();
    } else if (obj.key === 'bolt') {
        const g = ctx.createLinearGradient(x - r, 0, x + r, 0);
        g.addColorStop(0, '#5f7690'); g.addColorStop(0.45, obj.col); g.addColorStop(0.55, '#cfe0f0'); g.addColorStop(1, '#5f7690');
        ctx.fillStyle = g;
        ctx.beginPath(); ctx.roundRect(x - r * 0.45, y - r, r * 0.9, r * 1.9, 3); ctx.fill();     // shank
        ctx.beginPath(); ctx.moveTo(x - r * 0.8, y - r); ctx.lineTo(x + r * 0.8, y - r); ctx.lineTo(x + r * 0.55, y - r * 1.5); ctx.lineTo(x - r * 0.55, y - r * 1.5); ctx.closePath(); ctx.fill(); // hex head
        ctx.strokeStyle = 'rgba(0,0,0,.25)'; ctx.lineWidth = 1;
        for (let i = -0.7; i < 0.9; i += 0.28) { ctx.beginPath(); ctx.moveTo(x - r * 0.45, y + i * r); ctx.lineTo(x + r * 0.45, y + i * r + 3); ctx.stroke(); } // threads
    } else {
        const g = ctx.createLinearGradient(x - r, 0, x + r, 0);
        g.addColorStop(0, '#9aa8b6'); g.addColorStop(0.5, obj.col); g.addColorStop(1, '#8996a4');
        ctx.fillStyle = g;
        ctx.beginPath(); ctx.roundRect(x - r, y - r * 0.75, r * 2, r * 1.5, 4); ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,.22)'; ctx.lineWidth = 0.8;
        for (let i = -0.5; i < 0.6; i += 0.24) { ctx.beginPath(); ctx.moveTo(x - r * 0.9, y + i * r); ctx.lineTo(x + r * 0.9, y + i * r); ctx.stroke(); } // brushed
    }
    ctx.restore();
}

export default function DensityLab({ config = {}, onReady, onAddToNote }) {
    const [objKey, setObjKey] = useState(OBJECTS.some((o) => o.key === config.object) ? config.object : 'stone');
    const [step, setStep] = useState('pick');
    const cvRef = useRef(null);
    const animRef = useRef({ level: INITIAL, drop: 0 });
    const st = useRef({}); st.current = { objKey, step };

    const obj = OBJECTS.find((o) => o.key === objKey);
    const rho = obj.m / obj.V;

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, object: st.current.objKey, step: st.current.step }),
            setState: (s) => {
                if (s?.object && OBJECTS.some((o) => o.key === s.object)) setObjKey(s.object);
                if (s?.step && STEPS.includes(s.step)) setStep(s.step);
            },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, start = performance.now();
        const draw = (nowMs) => {
            const amb = (nowMs - start) / 1000;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const faint = cssVar('--ink-faint', '#68877A');
            const line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
            const amber = '#FBBF24';
            const { objKey: ok, step: sp } = st.current;
            const ob = OBJECTS.find((o) => o.key === ok);
            const a = animRef.current;
            const targetLevel = sp === 'submerged' || sp === 'computed' ? INITIAL + ob.V : INITIAL;
            a.level += (targetLevel - a.level) * 0.07;
            a.drop += ((sp === 'submerged' || sp === 'computed' ? 1 : 0) - a.drop) * 0.07;

            // ── Lab room + bench ──
            const wall = ctx.createLinearGradient(0, 0, 0, h);
            wall.addColorStop(0, '#20262e'); wall.addColorStop(0.6, '#272d35'); wall.addColorStop(1, '#20262a');
            ctx.fillStyle = wall; ctx.fillRect(0, 0, w, h);
            // soft window glow upper-left
            const win = ctx.createRadialGradient(w * 0.2, h * 0.1, 10, w * 0.2, h * 0.1, h * 0.9);
            win.addColorStop(0, 'rgba(210,225,240,.10)'); win.addColorStop(1, 'rgba(210,225,240,0)');
            ctx.fillStyle = win; ctx.fillRect(0, 0, w, h);
            const benchY = h - 30;
            const bench = ctx.createLinearGradient(0, benchY, 0, h);
            bench.addColorStop(0, '#39414c'); bench.addColorStop(1, '#2a3038');
            ctx.fillStyle = bench; ctx.fillRect(0, benchY, w, h - benchY);
            ctx.strokeStyle = 'rgba(255,255,255,.06)'; ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(0, benchY); ctx.lineTo(w, benchY); ctx.stroke();
            ctx.font = '600 10.5px Inter, sans-serif';

            // ── Electronic balance (left) ──
            const bx = w * 0.19, baseY = benchY;
            ctx.fillStyle = faint; ctx.textAlign = 'center';
            ctx.fillText('1 · mass from the balance', bx, 20);
            // body
            const bodyG = ctx.createLinearGradient(0, baseY - 30, 0, baseY);
            bodyG.addColorStop(0, '#e9edf2'); bodyG.addColorStop(1, '#c3cad3');
            ctx.fillStyle = bodyG;
            ctx.beginPath(); ctx.roundRect(bx - 54, baseY - 30, 108, 30, 5); ctx.fill();
            ctx.fillStyle = 'rgba(0,0,0,.15)'; ctx.beginPath(); ctx.roundRect(bx - 54, baseY - 4, 108, 6, 3); ctx.fill();
            // pan
            const panG = ctx.createLinearGradient(0, baseY - 40, 0, baseY - 30);
            panG.addColorStop(0, '#dfe4ea'); panG.addColorStop(1, '#aab3bd');
            ctx.fillStyle = panG;
            ctx.beginPath(); ctx.ellipse(bx, baseY - 34, 42, 7, 0, 0, Math.PI * 2); ctx.fill();
            if (a.drop < 0.5) drawObject(ctx, bx, baseY - 40, 15, ob, 1 - a.drop);
            // LCD
            ctx.fillStyle = '#0d130f';
            ctx.beginPath(); ctx.roundRect(bx - 40, baseY - 24, 80, 16, 3); ctx.fill();
            ctx.fillStyle = sp === 'pick' ? 'rgba(52,211,153,.35)' : '#4ef0a8';
            ctx.font = '700 12.5px "JetBrains Mono", monospace';
            ctx.fillText(sp === 'pick' ? '0.0 g' : `${ob.m}.0 g`, bx, baseY - 12);

            // ── Measuring cylinder (middle) ──
            const cx2 = w * 0.5, cw2 = 64, ctop = 32, cbot = benchY;
            const mlMax = 120;
            const yOf = (ml) => cbot - ((cbot - ctop - 8) * ml) / mlMax;
            ctx.fillStyle = faint; ctx.font = '600 10.5px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText('2 · volume from the rise', cx2, 20);
            const lvlY = yOf(a.level);
            // water body with gradient + shimmer
            const wat = ctx.createLinearGradient(0, lvlY, 0, cbot);
            wat.addColorStop(0, 'rgba(74,196,232,.42)'); wat.addColorStop(1, 'rgba(40,120,170,.30)');
            ctx.fillStyle = wat;
            ctx.fillRect(cx2 - cw2 / 2 + 2, lvlY, cw2 - 4, cbot - lvlY);
            // caustic shimmer
            ctx.strokeStyle = 'rgba(180,230,255,.18)'; ctx.lineWidth = 1;
            for (let i = 0; i < 3; i++) {
                const yy = lvlY + 12 + i * (cbot - lvlY) / 3.5 + Math.sin(amb * 1.5 + i) * 2;
                ctx.beginPath(); ctx.moveTo(cx2 - cw2 / 2 + 4, yy);
                ctx.bezierCurveTo(cx2 - cw2 / 6, yy - 3, cx2 + cw2 / 6, yy + 3, cx2 + cw2 / 2 - 4, yy); ctx.stroke();
            }
            // submerged object (refraction: slight offset + dim)
            if (a.drop > 0.02) {
                const oy = ctop + (cbot - 24 - ctop) * a.drop;
                drawObject(ctx, cx2 + Math.sin(amb) * 0.6, oy, 14, ob, 0.82);
            }
            // meniscus (curved surface)
            ctx.strokeStyle = 'rgba(190,235,255,.6)'; ctx.lineWidth = 1.6;
            ctx.beginPath(); ctx.moveTo(cx2 - cw2 / 2 + 2, lvlY + 2);
            ctx.quadraticCurveTo(cx2, lvlY - 3, cx2 + cw2 / 2 - 2, lvlY + 2); ctx.stroke();
            // glass walls with sheen
            const glassG = ctx.createLinearGradient(cx2 - cw2 / 2, 0, cx2 + cw2 / 2, 0);
            glassG.addColorStop(0, 'rgba(255,255,255,.28)'); glassG.addColorStop(0.15, 'rgba(255,255,255,.05)');
            glassG.addColorStop(0.85, 'rgba(255,255,255,.05)'); glassG.addColorStop(1, 'rgba(255,255,255,.16)');
            ctx.strokeStyle = glassG; ctx.lineWidth = 3;
            ctx.beginPath(); ctx.moveTo(cx2 - cw2 / 2, ctop); ctx.lineTo(cx2 - cw2 / 2, cbot); ctx.lineTo(cx2 + cw2 / 2, cbot); ctx.lineTo(cx2 + cw2 / 2, ctop); ctx.stroke();
            // vertical highlight streak
            ctx.strokeStyle = 'rgba(255,255,255,.22)'; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(cx2 - cw2 / 2 + 8, ctop + 10); ctx.lineTo(cx2 - cw2 / 2 + 8, cbot - 10); ctx.stroke();
            // scale
            ctx.font = '600 8px "JetBrains Mono", monospace'; ctx.textAlign = 'left';
            for (let ml = 0; ml <= mlMax; ml += 20) {
                const yy = yOf(ml);
                ctx.strokeStyle = 'rgba(255,255,255,.22)'; ctx.beginPath(); ctx.moveTo(cx2 + cw2 / 2 - 12, yy); ctx.lineTo(cx2 + cw2 / 2, yy); ctx.stroke();
                ctx.fillStyle = 'rgba(220,235,245,.7)'; ctx.fillText(String(ml), cx2 - cw2 / 2 + 3, yy - 2);
            }
            // initial marker + rise brace
            ctx.setLineDash([3, 3]); ctx.strokeStyle = faint; ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(cx2 - cw2 / 2 - 8, yOf(INITIAL)); ctx.lineTo(cx2 + cw2 / 2 + 8, yOf(INITIAL)); ctx.stroke(); ctx.setLineDash([]);
            ctx.fillStyle = faint; ctx.textAlign = 'right';
            ctx.fillText(`start ${INITIAL}`, cx2 - cw2 / 2 - 11, yOf(INITIAL) + 2.5);
            if (a.level > INITIAL + 0.5) {
                const y1 = yOf(a.level), y0 = yOf(INITIAL);
                ctx.strokeStyle = amber; ctx.lineWidth = 2;
                ctx.beginPath(); ctx.moveTo(cx2 + cw2 / 2 + 10, y0); ctx.lineTo(cx2 + cw2 / 2 + 16, y0);
                ctx.lineTo(cx2 + cw2 / 2 + 16, y1); ctx.lineTo(cx2 + cw2 / 2 + 10, y1); ctx.stroke();
                ctx.fillStyle = amber; ctx.textAlign = 'left'; ctx.font = '700 10px "JetBrains Mono", monospace';
                ctx.fillText(`rise = ${Math.round(a.level - INITIAL)} cm³`, cx2 + cw2 / 2 + 21, (y0 + y1) / 2 + 3);
                ctx.font = '600 8.5px Inter, sans-serif'; ctx.fillStyle = faint;
                ctx.fillText('← the volume', cx2 + cw2 / 2 + 21, (y0 + y1) / 2 + 15);
            }

            // ── Calc panel (right) ──
            const px2 = w * 0.85;
            ctx.fillStyle = 'rgba(160,200,175,.04)';
            ctx.beginPath(); ctx.roundRect(px2 - 62, 30, 124, 96, 8); ctx.fill();
            ctx.font = '600 10.5px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillStyle = faint; ctx.fillText('3 · divide', px2, 46);
            ctx.font = '700 12px "JetBrains Mono", monospace';
            const haveV = sp === 'submerged' || sp === 'computed';
            const lines = [
                [`m = ${sp === 'pick' ? '?' : ob.m + ' g'}`, sp === 'pick' ? faint : acc],
                [`V = ${haveV ? `${ob.V} cm³` : '?'}`, haveV ? amber : faint],
                [`ρ = ${sp === 'computed' ? `${(ob.m / ob.V).toFixed(1)} g/cm³` : '?'}`, sp === 'computed' ? acc : faint],
            ];
            lines.forEach(([txt, col], i) => { ctx.fillStyle = col; ctx.fillText(txt, px2, 74 + i * 24); });

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const advance = () => {
        setStep((prev) => prev === 'pick' ? 'weighed' : prev === 'weighed' ? 'submerged' : prev === 'submerged' ? 'computed' : 'pick');
    };
    const actionLabel = { pick: '⚖ Weigh it', weighed: '↓ Submerge it', submerged: '÷ Divide', computed: '↺ New object' }[step];

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 9' }}>
                    <span className="cw-badge">Mass · rise · divide</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label={step === 'computed' ? 'density (a property of the material)' : 'determination in progress'}
                      value={step === 'computed' ? rho.toFixed(1) : ['—', `${obj.m} g`, `${obj.V} cm³ rise`, ''][STEPS.indexOf(step)] || '—'}
                      unit={step === 'computed' ? 'g/cm³' : ''} tone="acc"
                      sub={<>object: <b>{obj.label}</b>{step !== 'pick' && <> · m = <b>{obj.m} g</b></>}{(step === 'submerged' || step === 'computed') && <> · V = <b>{obj.V} cm³</b></>}</>} />
                <div className="cw-btnrow">
                    {OBJECTS.map((o) => (
                        <button key={o.key} className={'cw-btn ' + (o.key === objKey ? 'cw-btn-save' : 'cw-btn-ghost')}
                                onClick={() => { setObjKey(o.key); setStep('pick'); }}>
                            {o.label}
                        </button>
                    ))}
                </div>
                <div className="cw-btnrow">
                    <Button variant="save" onClick={advance}>{actionLabel}</Button>
                </div>
                <Flag kind="neutral">
                    The cylinder's final reading is water <b>plus</b> object — the object's volume is the <b>rise</b>
                    (final − start), bracketed in amber. Every determination ends the same way: mass from the balance,
                    volume by the shape's method, one division.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, object: objKey, step })}>📌 Save this determination to my notes</button>
                )}
            </div>
        </div>
    );
}
