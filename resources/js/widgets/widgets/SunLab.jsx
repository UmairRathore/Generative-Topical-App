import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag } from '../primitives.jsx';

// ── Widget: sun_lab ──────────────────────────────────────────────────────────
// Bespoke 6.2.1 hero (immersive 2D). The Sun as a star.
//   STAR: the Sun is a medium-size star made mostly of hydrogen and helium, radiating
//         most of its energy in the infrared, visible and ultraviolet — shown as a
//         spectrum bar with those three bands highlighted, plus a composition split.
//   FUSION: in the core, hydrogen nuclei FUSE into helium, releasing the energy that
//         powers the star (gravity pulling in is balanced by the outward push of the
//         hot core).
// config: { mode }

export default function SunLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['star', 'fusion'].includes(config.mode) ? config.mode : 'star');
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode }),
            setState: (s) => { if (['star', 'fusion'].includes(s?.mode)) setMode(s.mode); },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf;
        const draw = (now) => {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight;
            if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const faint = cssVar('--ink-faint', '#68877A'), ink = cssVar('--ink-soft', '#A6C4B3');
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', cyan = '#38BDF8', rose = '#FB7185', violet = '#a78bfa';
            const S = st.current, t = now / 1000;
            ctx.fillStyle = '#05070d'; ctx.fillRect(0, 0, w, h);
            for (let i = 0; i < 50; i++) { const sx = (i * 137.5) % w, sy = (i * 61.7) % h; ctx.fillStyle = `rgba(255,255,255,${0.1 + 0.1 * Math.sin(i + t)})`; ctx.fillRect(sx, sy, 1.2, 1.2); }
            ctx.textAlign = 'center';

            if (S.mode === 'star') {
                const cx = w * 0.28, cy = h * 0.44, R = Math.min(w * 0.14, h * 0.28);
                const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, R * 2.2); g.addColorStop(0, 'rgba(255,241,180,1)'); g.addColorStop(0.35, 'rgba(251,191,36,0.9)'); g.addColorStop(0.7, 'rgba(251,146,60,0.4)'); g.addColorStop(1, 'rgba(251,146,60,0)');
                ctx.fillStyle = g; ctx.beginPath(); ctx.arc(cx, cy, R * 2.2, 0, 7); ctx.fill();
                ctx.fillStyle = '#FDB813'; ctx.beginPath(); ctx.arc(cx, cy, R, 0, 7); ctx.fill();
                // surface granulation flicker
                for (let i = 0; i < 24; i++) { const a = i * 0.7 + t * 0.2, rr = R * (0.3 + 0.6 * ((i * 13) % 10) / 10); ctx.fillStyle = `rgba(255,${180 + (i % 5) * 12},80,0.25)`; ctx.beginPath(); ctx.arc(cx + Math.cos(a) * rr, cy + Math.sin(a) * rr, 3, 0, 7); ctx.fill(); }
                ctx.fillStyle = ink; ctx.font = '700 12px Inter'; ctx.fillText('the Sun', cx, cy + R + 22);
                ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.fillText('a medium-size star', cx, cy + R + 38);
                // composition chip
                ctx.textAlign = 'left'; ctx.fillStyle = acc; ctx.font = '700 11px Inter'; ctx.fillText('Made mostly of:', w * 0.52, h * 0.2);
                ctx.fillStyle = amber; ctx.font = '700 12px Inter'; ctx.fillText('• hydrogen (H)  ~74%', w * 0.52, h * 0.26);
                ctx.fillStyle = cyan; ctx.fillText('• helium (He)  ~24%', w * 0.52, h * 0.31);
                // radiation spectrum bar
                const bx = w * 0.52, by = h * 0.52, bw = w * 0.42, bh = 22;
                const bands = [['radio', '#334155', 0], ['micro', '#475569', 0], ['IR', '#ef4444', 1], ['visible', '#22c55e', 1], ['UV', '#8b5cf6', 1], ['X-ray', '#475569', 0], ['γ', '#334155', 0]];
                bands.forEach((b, i) => { const x = bx + bw * i / bands.length; ctx.fillStyle = b[2] ? b[1] : 'rgba(71,85,105,0.5)'; ctx.fillRect(x, by, bw / bands.length - 2, bh); ctx.fillStyle = b[2] ? ink : faint; ctx.font = (b[2] ? '700 ' : '600 ') + '9px Inter'; ctx.textAlign = 'center'; ctx.fillText(b[0], x + bw / bands.length / 2, by + bh + 12); });
                ctx.textAlign = 'left'; ctx.fillStyle = ink; ctx.font = '700 11px Inter'; ctx.fillText('Radiates MOST of its energy here →', bx, by - 8);
                ctx.fillStyle = amber; ctx.font = '600 10px Inter'; ctx.fillText('infrared · visible · ultraviolet', bx, by + bh + 30);
                // rays leaving the sun
                for (let i = 0; i < 12; i++) { const a = i * 0.52; const lp = (t * 0.4 + i / 12) % 1; ctx.fillStyle = `rgba(253,224,71,${0.6 * (1 - lp)})`; ctx.beginPath(); ctx.arc(cx + Math.cos(a) * (R + lp * 40), cy + Math.sin(a) * (R + lp * 40), 1.6, 0, 7); ctx.fill(); }
            }

            else { // fusion core
                const cx = w * 0.34, cy = h * 0.46, R = Math.min(w * 0.2, h * 0.36);
                // core glow
                const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, R); g.addColorStop(0, 'rgba(255,241,180,0.9)'); g.addColorStop(1, 'rgba(251,146,60,0.1)'); ctx.fillStyle = g; ctx.beginPath(); ctx.arc(cx, cy, R, 0, 7); ctx.fill();
                ctx.strokeStyle = 'rgba(251,191,36,0.5)'; ctx.lineWidth = 1; ctx.beginPath(); ctx.arc(cx, cy, R, 0, 7); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.fillText("the Sun's core", cx, cy - R - 8);
                // hydrogen nuclei drifting inward, fusing at centre with a flash
                const period = 2.6, ph = (t % period) / period;
                for (let i = 0; i < 4; i++) { const a = i * 1.57 + t * 0.3; const rr = R * (0.85 - 0.7 * ph); const px = cx + Math.cos(a) * rr, py = cy + Math.sin(a) * rr; ctx.fillStyle = rose; ctx.beginPath(); ctx.arc(px, py, 4, 0, 7); ctx.fill(); }
                if (ph > 0.7) { const fa = (ph - 0.7) / 0.3; const fg = ctx.createRadialGradient(cx, cy, 0, cx, cy, 30); fg.addColorStop(0, `rgba(251,191,36,${0.8 * (1 - fa)})`); fg.addColorStop(1, 'rgba(0,0,0,0)'); ctx.fillStyle = fg; ctx.beginPath(); ctx.arc(cx, cy, 30, 0, 7); ctx.fill(); ctx.fillStyle = cyan; ctx.beginPath(); ctx.arc(cx, cy, 7, 0, 7); ctx.fill(); ctx.fillStyle = ink; ctx.font = '700 9px Inter'; ctx.fillText('helium', cx, cy + 20); }
                // gravity (in) vs pressure (out) arrows
                for (let i = 0; i < 6; i++) { const a = i * 1.047; const ex = cx + Math.cos(a) * (R + 6), ey = cy + Math.sin(a) * (R + 6); ctx.strokeStyle = rose; ctx.lineWidth = 1.6; ctx.beginPath(); ctx.moveTo(cx + Math.cos(a) * (R + 30), cy + Math.sin(a) * (R + 30)); ctx.lineTo(ex, ey); ctx.stroke(); ctx.strokeStyle = amber; ctx.beginPath(); ctx.moveTo(cx + Math.cos(a) * (R * 0.5), cy + Math.sin(a) * (R * 0.5)); ctx.lineTo(cx + Math.cos(a) * (R * 0.85), cy + Math.sin(a) * (R * 0.85)); ctx.stroke(); }
                // labels
                ctx.textAlign = 'left'; ctx.fillStyle = rose; ctx.font = '700 11px Inter'; ctx.fillText('gravity pulls IN', w * 0.62, h * 0.3);
                ctx.fillStyle = amber; ctx.fillText('hot fusion pushes OUT', w * 0.62, h * 0.36);
                ctx.fillStyle = acc; ctx.font = '800 13px Inter'; ctx.fillText('H  →  He  + energy', w * 0.62, h * 0.5);
                ctx.fillStyle = ink; ctx.font = '600 11px Inter'; ctx.fillText('nuclear FUSION of hydrogen', w * 0.62, h * 0.57);
                ctx.fillStyle = faint; ctx.fillText('into helium powers the star', w * 0.62, h * 0.62);
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
                    <span className="cw-badge">{mode === 'star' ? 'The Sun: a medium-size star' : "Fusion in the Sun's core"}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'star' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('star')}>The Sun as a star</button>
                    <button className={'cw-btn ' + (mode === 'fusion' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('fusion')}>Fusion core</button>
                </div>
                {mode === 'star'
                    ? <Stat label="The Sun" value="medium-size star · H + He" tone="acc" sub={<>The Sun is a <b>medium-size star</b> made mostly of <b>hydrogen</b> and <b>helium</b>. It radiates <b>most of its energy</b> in the <b>infrared</b>, <b>visible</b> and <b>ultraviolet</b> parts of the spectrum.</>} />
                    : <Stat label="Powered by fusion" value="H → He + energy" tone="acc" sub={<>Stars are powered by <b>nuclear reactions</b>. In a <b>stable star</b> like the Sun, <b>hydrogen</b> nuclei <b>fuse into helium</b>, releasing the energy that makes it shine. The outward push of the hot core balances gravity pulling in.</>} />}
                <Flag kind="neutral">
                    The <b>Sun</b> is a <b>medium-size star</b>, made mostly of <b>hydrogen and helium</b>, and it radiates most of its energy in the
                    <b> infrared</b>, <b>visible</b> and <b>ultraviolet</b> regions of the electromagnetic spectrum. Stars are powered by <b>nuclear
                    reactions</b> that release energy: in a <b>stable star</b>, the reaction is the <b>fusion of hydrogen into helium</b>.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
