import React, { useEffect, useMemo, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag, Options } from '../primitives.jsx';

// ── Widget: ultrasound_echo ──────────────────────────────────────────────────
// Pulse-echo depth finding (ultrasound / sonar). A pulse fired from the sensor
// reflects off internal features; each echo's return time gives its depth,
// depth = speed × time ÷ 2 (halved because the pulse travels down AND back).
// Matches the exam figure: sensor on a metal block, a crack, the bottom surface,
// and x = the gap between them. Drag the echo times to explore.
// config: { speed, unit, t1, t2, label1, label2, result, options }

const VW = 1000, VH = 560;
const BL = 170, BR = 820, TOP = 175, BOT = 470;   // block box; BOT = bottom surface

function arrowV(ctx, x, y1, y2, col) {
    ctx.strokeStyle = col; ctx.fillStyle = col; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(x, y1); ctx.lineTo(x, y2); ctx.stroke();
    [[y1, 1], [y2, -1]].forEach(([y, d]) => { ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x - 5, y + d * 9); ctx.lineTo(x + 5, y + d * 9); ctx.closePath(); ctx.fill(); });
}

export default function UltrasoundEcho({ config = {}, onReady, onAddToNote }) {
    const { speed = 5200, unit = 'm', label1 = 'crack', label2 = 'bottom surface', result = {} } = config;
    const [t1, setT1] = useState(config.t1 ?? 1e-5);
    const [t2, setT2] = useState(config.t2 ?? 1.5e-5);
    const [probe, setProbe] = useState(null);
    const stRef = useRef({ t1, t2 }); stRef.current = { t1, t2 };
    const cvRef = useRef(null);

    const d1 = (speed * t1) / 2, d2 = (speed * t2) / 2, x = d2 - d1;

    useEffect(() => {
        onReady && onReady({ getState: () => ({ ...config, t1, t2 }), setState: (s) => { if (s?.t1 != null) setT1(s.t1); if (s?.t2 != null) setT2(s.t2); } });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const frame = (time) => {
            const s = stRef.current, dd1 = (speed * s.t1) / 2, dd2 = (speed * s.t2) / 2, xx = dd2 - dd1;
            const g = cv.__g;
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399');
            const scaleD = (BOT - TOP) / (dd2 || 1);          // px per metre, bottom = dd2
            const crackY = TOP + dd1 * scaleD, sx = (BL + BR) / 2, crackX = BL + 150;

            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            // metal block
            const mg = ctx.createLinearGradient(0, TOP, 0, BOT); mg.addColorStop(0, '#3a4653'); mg.addColorStop(1, '#242e39');
            ctx.fillStyle = mg; ctx.strokeStyle = '#5a6675'; ctx.lineWidth = 2; ctx.fillRect(BL, TOP, BR - BL, BOT - TOP); ctx.strokeRect(BL, TOP, BR - BL, BOT - TOP);
            ctx.fillStyle = faint; ctx.font = '600 16px Inter, sans-serif'; ctx.textAlign = 'left'; ctx.fillText('metal', BR + 14, (TOP + BOT) / 2);
            ctx.textAlign = 'center'; ctx.fillText('bottom surface', (BL + BR) / 2, BOT + 22);

            // sensor on top
            ctx.fillStyle = '#7fd3ff'; ctx.fillRect(sx - 22, TOP - 50, 44, 50);
            ctx.fillStyle = faint; ctx.fillText('sensor', sx, TOP - 58);

            // crack
            ctx.fillStyle = '#fbbf24'; ctx.beginPath(); ctx.ellipse(crackX, crackY, 34, 7, 0, 0, 6.283); ctx.fill();
            ctx.strokeStyle = faint; ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(crackX - 30, crackY); ctx.lineTo(BL - 4, crackY - 46); ctx.stroke();
            ctx.fillStyle = ink; ctx.textAlign = 'right'; ctx.fillText(label1, BL - 8, crackY - 50);

            // depth markers d1, d2 (left side)
            ctx.strokeStyle = 'rgba(127,211,255,.5)'; ctx.setLineDash([3, 3]); ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(BL, crackY); ctx.lineTo(BR, crackY); ctx.stroke(); ctx.setLineDash([]);

            // x dimension (crack → bottom) near the centre
            arrowV(ctx, sx + 34, crackY, BOT, acc);
            ctx.fillStyle = acc; ctx.font = '700 19px "JetBrains Mono", monospace'; ctx.textAlign = 'left';
            ctx.fillText('x = ' + xx.toFixed(3) + ' ' + unit, sx + 46, (crackY + BOT) / 2 + 6);

            // animated pulse: down from sensor, echo up; flashes crack + bottom
            const period = 2600, ph = (time % period) / period;
            const down = ph < 0.5, p = down ? ph * 2 : (1 - ph) * 2;   // 0→1→0
            const py = TOP + p * (BOT - TOP);
            ctx.strokeStyle = `rgba(127,211,255,${down ? 0.95 : 0.55})`; ctx.lineWidth = 3.5; ctx.lineCap = 'round';
            ctx.beginPath(); ctx.moveTo(sx - 16, py); ctx.lineTo(sx + 16, py); ctx.stroke();
            // glow the reflector the pulse is passing
            const near = (y) => Math.abs(py - y) < 10;
            if (near(crackY)) { ctx.fillStyle = 'rgba(251,191,36,.9)'; ctx.beginPath(); ctx.ellipse(crackX, crackY, 40, 10, 0, 0, 6.283); ctx.fill(); }
            if (near(BOT)) { ctx.strokeStyle = 'rgba(127,211,255,.9)'; ctx.lineWidth = 4; ctx.beginPath(); ctx.moveTo(BL, BOT); ctx.lineTo(BR, BOT); ctx.stroke(); }

            raf = requestAnimationFrame(frame);
        };
        raf = requestAnimationFrame(frame);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [speed, unit, label1, label2]);

    const us = (t) => (t * 1e5).toFixed(1);   // display in ×10⁻⁵ s

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Live · pulse echo</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label={result.label || 'Gap x'} value={x.toFixed(3)} unit={result.unit || unit} tone="acc"
                      sub={<>{label1} depth <b>{d1.toFixed(3)}</b> · {label2} depth <b>{d2.toFixed(3)} {unit}</b></>} />
                <div className="cw-calc-formula" style={{ margin: 0 }}>
                    <div className="cw-calc-flabel">Working</div>
                    <div className="cw-calc-expr">depth = speed × time ÷ 2</div>
                    <div className="cw-calc-expr cw-calc-sub">x = {d2.toFixed(3)} − {d1.toFixed(3)} = {x.toFixed(3)} {unit}</div>
                </div>
                <Slider label={`Echo from ${label1}`} value={+us(t1)} min={0} max={3} step={0.1} tone="#fbbf24"
                        format={(v) => v.toFixed(1) + ' ×10⁻⁵ s'} onChange={(v) => setT1(v * 1e-5)} />
                <Slider label={`Echo from ${label2}`} value={+us(t2)} min={0} max={3} step={0.1} tone="#7fd3ff"
                        format={(v) => v.toFixed(1) + ' ×10⁻⁵ s'} onChange={(v) => setT2(v * 1e-5)} />
                {config.options?.length > 0 && (
                    <Options options={config.options} lead="Which distance is x?" onPick={(o) => setProbe(o)} />
                )}
                <Flag kind="neutral">Each echo returns after travelling <b>down and back</b>, so its depth is <b>speed × time ÷ 2</b>. The gap x is the difference of the two depths.</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, t1, t2 })}>📌 Save this diagram to my notes</button>)}
            </div>
        </div>
    );
}
