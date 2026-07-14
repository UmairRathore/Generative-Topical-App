import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag } from '../primitives.jsx';

// ── Widget: magnet_lab ───────────────────────────────────────────────────────
// Bespoke Magnetism hero (5054 · 4.1). Three modes:
//   POLES     — two bar magnets face each other; flip one and watch like poles
//               repel / unlike poles attract, with force arrows; a soft-iron bar
//               on the pole shows induced magnetism (a paperclip clings to it).
//   MATERIALS — a magnet tests a tray of materials (iron, steel, nickel vs
//               copper, aluminium, plastic): magnetic ones are attracted; a
//               soft-iron / steel toggle shows temporary vs permanent magnetism.
//   FIELD     — a bar magnet with its field TRACED from the real dipole field:
//               streamlines from N to S (arrows = direction on an N pole), iron
//               filings, and a plotting compass aligning to the field; lines
//               crowd at the poles (stronger field).
//
// 2D by design (bar-magnet fields are drawn in a plane; 3D is reserved for the
// motor effect in 4.5). config: { mode, flipped, view } · Notes carry the same.

const MATERIALS = [
    { name: 'iron nail', magnetic: true },
    { name: 'steel clip', magnetic: true },
    { name: 'nickel coin', magnetic: true },
    { name: 'copper wire', magnetic: false },
    { name: 'aluminium', magnetic: false },
    { name: 'plastic', magnetic: false },
];

export default function MagnetLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['poles', 'materials', 'field'].includes(config.mode) ? config.mode : 'poles');
    const [flipped, setFlipped] = useState(!!config.flipped);
    const [view, setView] = useState(['lines', 'filings', 'compass'].includes(config.view) ? config.view : 'lines');
    const [soft, setSoft] = useState(config.soft !== false); // materials: soft iron (temporary) vs steel
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, flipped, view, soft };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, flipped: st.current.flipped, view: st.current.view, soft: st.current.soft }),
            setState: (s) => {
                if (['poles', 'materials', 'field'].includes(s?.mode)) setMode(s.mode);
                if (typeof s?.flipped === 'boolean') setFlipped(s.flipped);
                if (['lines', 'filings', 'compass'].includes(s?.view)) setView(s.view);
                if (typeof s?.soft === 'boolean') setSoft(s.soft);
            },
        });
    }, [onReady]); // eslint-disable-line

    const attract = flipped; // flipped => unlike poles face => attract

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, t0 = performance.now();

        const draw = (now) => {
            const amb = (now - t0) / 1000;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight;
            if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', rose = '#FB7185';
            const RED = '#E5484D', BLUE = '#4D7BFF';
            const S = st.current;
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#12161d'); bg.addColorStop(1, '#0b0e14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);

            // draw a bar magnet centred at (cx,cy), half-length L, half-height mh; nLeft = N on left
            const barMagnet = (cx, cy, L, mh, nLeft, labelN = 'N', labelS = 'S') => {
                const left = nLeft ? RED : BLUE, right = nLeft ? BLUE : RED;
                const g = ctx.createLinearGradient(cx - L, 0, cx + L, 0);
                g.addColorStop(0, left); g.addColorStop(0.49, left); g.addColorStop(0.51, right); g.addColorStop(1, right);
                ctx.fillStyle = g;
                ctx.beginPath(); ctx.rect(cx - L, cy - mh, 2 * L, 2 * mh); ctx.fill();
                ctx.strokeStyle = 'rgba(255,255,255,.25)'; ctx.lineWidth = 1.2; ctx.stroke();
                ctx.fillStyle = '#fff'; ctx.font = `700 ${Math.min(18, mh * 1.1)}px Inter, sans-serif`; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                ctx.fillText(nLeft ? labelN : labelS, cx - L * 0.55, cy);
                ctx.fillText(nLeft ? labelS : labelN, cx + L * 0.55, cy);
                ctx.textBaseline = 'alphabetic';
            };
            const arrow = (x0, y0, x1, y1, col, wide = 2) => {
                ctx.strokeStyle = col; ctx.lineWidth = wide; ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke();
                const a = Math.atan2(y1 - y0, x1 - x0);
                ctx.fillStyle = col; ctx.beginPath(); ctx.moveTo(x1, y1);
                ctx.lineTo(x1 - Math.cos(a - 0.4) * 9, y1 - Math.sin(a - 0.4) * 9);
                ctx.lineTo(x1 - Math.cos(a + 0.4) * 9, y1 - Math.sin(a + 0.4) * 9); ctx.closePath(); ctx.fill();
            };

            if (S.mode === 'poles') {
                const cy = h * 0.42, L = Math.min(w * 0.16, 90), mh = Math.min(h * 0.11, 30);
                const gap = S.flipped ? 34 + Math.sin(amb * 2) * 4 : 60 + Math.sin(amb * 2) * 4;
                const move = (S.flipped ? -1 : 1) * (2 + Math.sin(amb * 2) * 2);
                const lx = w * 0.32 - move, rx = w * 0.68 + move;
                barMagnet(lx, cy, L, mh, true);                 // left magnet: N-left, S-right (S faces gap)
                barMagnet(rx, cy, L, mh, S.flipped ? true : false); // right: flipped => N faces gap (N..S) unlike->attract
                // the facing poles: left shows S (right end), right shows (flipped? N : S)
                const leftFace = 'S', rightFace = S.flipped ? 'N' : 'S';
                // force arrows
                const mid = (lx + L + rx - L) / 2, ay = cy;
                if (S.flipped) { // attract: arrows point inward
                    arrow(lx + L + 6, ay - mh - 14, mid - 6, ay - mh - 14, acc, 2.4);
                    arrow(rx - L - 6, ay - mh - 14, mid + 6, ay - mh - 14, acc, 2.4);
                } else { // repel: arrows point outward
                    arrow(mid - 6, ay - mh - 14, lx + L - 8, ay - mh - 14, rose, 2.4);
                    arrow(mid + 6, ay - mh - 14, rx - L + 8, ay - mh - 14, rose, 2.4);
                }
                ctx.fillStyle = S.flipped ? acc : rose; ctx.font = '700 13px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(S.flipped ? `unlike poles (${leftFace}–${rightFace}) ATTRACT` : `like poles (${leftFace}–${rightFace}) REPEL`, w * 0.5, cy - mh - 30);
                // induced magnetism vignette (bottom): soft-iron bar on a magnet's N pole holds a paperclip chain
                const iy = h * 0.80;
                barMagnet(w * 0.30, iy, L * 0.8, mh * 0.8, true);
                const px = w * 0.30 + L * 0.8;
                ctx.fillStyle = '#9aa4b0'; ctx.fillRect(px, iy - 7, 54, 14);           // soft-iron bar
                ctx.fillStyle = '#fff'; ctx.font = '700 8px Inter'; ctx.textAlign = 'center';
                ctx.fillText('induced S', px + 8, iy + 2); ctx.fillText('N', px + 48, iy + 2);
                // paperclip chain hanging from far end
                ctx.strokeStyle = '#c8d0da'; ctx.lineWidth = 2;
                for (let k = 0; k < 3; k++) { ctx.beginPath(); ctx.ellipse(px + 54, iy + 12 + k * 12, 4, 6, 0, 0, Math.PI * 2); ctx.stroke(); }
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText('induced magnetism: the soft iron becomes a magnet and holds the clips', w * 0.30 - L * 0.8, iy + h * 0.13);
            } else if (S.mode === 'materials') {
                const my = h * 0.5, L = Math.min(w * 0.13, 74), mh = Math.min(h * 0.1, 26);
                barMagnet(w * 0.15, my, L, mh, true);
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('magnet', w * 0.15, my - mh - 8);
                const x0 = w * 0.34, x1 = w * 0.95, dx = (x1 - x0) / MATERIALS.length;
                MATERIALS.forEach((m, i) => {
                    const bx = x0 + dx * (i + 0.5);
                    const pull = m.magnetic ? Math.min(14, 6 + Math.sin(amb * 3 + i) * 3) : 0;
                    ctx.fillStyle = m.magnetic ? '#c8d0da' : 'rgba(150,160,175,.6)';
                    ctx.beginPath(); ctx.roundRect(bx - 16 - pull, my - 12, 32, 24, 5); ctx.fill();
                    ctx.fillStyle = m.magnetic ? acc : faint; ctx.font = '700 10px Inter'; ctx.textAlign = 'center';
                    ctx.fillText(m.magnetic ? '✓' : '✗', bx - pull, my + 4);
                    ctx.fillStyle = ink; ctx.font = '600 8.5px Inter, sans-serif';
                    ctx.fillText(m.name, bx, my + 30);
                });
                // temporary vs permanent bar
                const ry = h * 0.86;
                ctx.fillStyle = ink; ctx.font = '700 10px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText(S.soft ? 'soft iron — TEMPORARY magnet' : 'steel — PERMANENT magnet', w * 0.08, ry - 8);
                const retain = S.soft ? 0.5 + 0.5 * Math.sin(amb * 1.2) : 0.85;
                ctx.fillStyle = 'rgba(255,255,255,.12)'; ctx.fillRect(w * 0.08, ry, w * 0.5, 10);
                ctx.fillStyle = S.soft ? amber : acc; ctx.fillRect(w * 0.08, ry, w * 0.5 * retain, 10);
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif';
                ctx.fillText(S.soft ? 'strong when near a magnet, but loses it when removed' : 'harder to magnetise, but keeps its magnetism', w * 0.08, ry + 24);
            } else {
                // FIELD mode — real dipole field traced from two point poles
                const cx = w * 0.5, cy = h * 0.46, L = Math.min(w * 0.17, 96), mh = Math.min(h * 0.09, 26);
                const N = { x: cx - L + 6, y: cy }, Sp = { x: cx + L - 6, y: cy };
                const magL = cx - L, magR = cx + L, magT = cy - mh, magB = cy + mh;
                const field = (x, y) => {
                    const dnx = x - N.x, dny = y - N.y, rn = Math.hypot(dnx, dny) + 1e-3;
                    const dsx = x - Sp.x, dsy = y - Sp.y, rs = Math.hypot(dsx, dsy) + 1e-3;
                    return { x: dnx / (rn * rn * rn) - dsx / (rs * rs * rs), y: dny / (rn * rn * rn) - dsy / (rs * rs * rs), rs };
                };
                const inMag = (x, y) => x > magL + 2 && x < magR - 2 && y > magT + 2 && y < magB - 2;

                if (S.view === 'filings') {
                    // Real iron filings clump into fine curved streaks ALONG the field —
                    // so trace many closely-spaced streamlines (denser where they crowd,
                    // near the poles) and draw them broken, like filings, not a quiver grid.
                    const NF = 34;
                    ctx.setLineDash([3.5, 2.8]);
                    for (let i = 0; i < NF; i++) {
                        const ang = -Math.PI + (i + 0.5) / NF * 2 * Math.PI;
                        let x = N.x + Math.cos(ang) * (mh + 4), y = N.y + Math.sin(ang) * (mh + 4);
                        if (inMag(x, y)) continue;
                        ctx.beginPath(); ctx.moveTo(x, y);
                        for (let step = 0; step < 560; step++) {
                            const f = field(x, y); const m = Math.hypot(f.x, f.y); if (m < 1e-9) break;
                            const ux = f.x / m, uy = f.y / m;
                            x += ux * 2.6; y += uy * 2.6;
                            if (x < 2 || x > w - 2 || y < 2 || y > h - 6) { ctx.lineTo(x, y); break; }
                            if (inMag(x, y)) break;
                            ctx.lineTo(x, y);
                            if (f.rs < mh + 4) break; // reached S pole
                        }
                        ctx.strokeStyle = 'rgba(216,230,248,.5)'; ctx.lineWidth = 1.15; ctx.stroke();
                    }
                    ctx.setLineDash([]);
                } else {
                    // streamlines from around the N pole
                    const NLINES = 9;
                    for (let i = 0; i < NLINES; i++) {
                        const ang = -Math.PI + (i + 0.5) / NLINES * 2 * Math.PI;
                        let x = N.x + Math.cos(ang) * (mh + 6), y = N.y + Math.sin(ang) * (mh + 6);
                        if (inMag(x, y)) continue;
                        ctx.strokeStyle = 'rgba(120,200,255,.75)'; ctx.lineWidth = 1.6; ctx.beginPath(); ctx.moveTo(x, y);
                        let arrowAt = 26;
                        for (let step = 0; step < 520; step++) {
                            const f = field(x, y); const m = Math.hypot(f.x, f.y); if (m < 1e-9) break;
                            const ux = f.x / m, uy = f.y / m;
                            x += ux * 3; y += uy * 3;
                            if (x < 2 || x > w - 2 || y < 2 || y > h - 6) { ctx.lineTo(x, y); break; }
                            if (inMag(x, y)) break;
                            ctx.lineTo(x, y);
                            if (--arrowAt <= 0 && f.rs > 24) { arrow(x - ux * 6, y - uy * 6, x, y, 'rgba(150,215,255,.95)', 1.4); arrowAt = 40; }
                            if (f.rs < mh + 4) break; // reached S pole
                        }
                        ctx.stroke();
                    }
                }
                barMagnet(cx, cy, L, mh, true);

                if (S.view === 'compass') {
                    // a plotting compass riding along the top field line
                    const t = (amb * 0.15) % 1;
                    let x = N.x + Math.cos(-2.2) * (mh + 6), y = N.y + Math.sin(-2.2) * (mh + 6);
                    const target = Math.floor(t * 150) + 4;
                    let fx = 1, fy = 0;
                    for (let step = 0; step < target; step++) {
                        const f = field(x, y); const m = Math.hypot(f.x, f.y); if (m < 1e-9) break;
                        fx = f.x / m; fy = f.y / m; x += fx * 3; y += fy * 3;
                        if (inMag(x, y) || x < 6 || x > w - 6 || y < 6 || y > h - 6) break;
                    }
                    ctx.fillStyle = 'rgba(20,26,34,.85)'; ctx.strokeStyle = 'rgba(255,255,255,.5)'; ctx.lineWidth = 1.5;
                    ctx.beginPath(); ctx.arc(x, y, 13, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
                    arrow(x - fx * 9, y - fy * 9, x + fx * 10, y + fy * 10, RED, 2.2); // red points along field (toward S)
                    ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
                    ctx.fillText('the compass needle lines up with the field', w * 0.5, h - 12);
                }
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                if (S.view === 'filings') ctx.fillText('iron filings line up along the field, tracing its shape — densest at the poles', w * 0.5, h - 12);
                else if (S.view === 'lines') ctx.fillText('field lines run N → S; they crowd at the poles where the field is strongest', w * 0.5, h - 12);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const badge = { poles: 'Poles & induced magnetism · attract / repel', materials: 'Magnetic materials · temporary vs permanent', field: 'Magnetic field · lines, filings & compass' }[mode];

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{badge}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'poles' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('poles')}>🧲 Poles</button>
                    <button className={'cw-btn ' + (mode === 'materials' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('materials')}>🔩 Materials</button>
                    <button className={'cw-btn ' + (mode === 'field' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('field')}>🧭 Field</button>
                </div>

                {mode === 'poles' && (
                    <>
                        <Stat label={attract ? 'unlike poles' : 'like poles'} value={attract ? 'attract' : 'repel'} tone={attract ? 'acc' : 'warn'}
                              sub={<>two <b>like</b> poles (N–N or S–S) <b>repel</b>; two <b>unlike</b> poles (N–S) <b>attract</b>. A magnet also <b>attracts magnetic materials</b> by inducing magnetism in them.</>} />
                        <button className="cw-btn cw-btn-ghost" onClick={() => setFlipped(!flipped)}>↺ Flip the right-hand magnet</button>
                        <Flag kind="neutral">
                            A magnet has a <b>north (N)</b> and a <b>south (S)</b> pole. <b>Like poles repel, unlike poles attract.</b> A
                            magnet also attracts an unmagnetised magnetic material (like soft iron) because it <b>induces magnetism</b> in
                            it — the near end becomes an opposite pole, so it is pulled in and can itself hold a chain of paperclips.
                        </Flag>
                    </>
                )}

                {mode === 'materials' && (
                    <>
                        <Stat label="magnetic vs non-magnetic" value="iron · steel · nickel" tone="acc"
                              sub={<><b>Magnetic materials</b> (iron, steel, nickel, cobalt) are attracted to a magnet; <b>non-magnetic</b> ones (copper, aluminium, plastic, wood) are not.</>} />
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (soft ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setSoft(true)}>Soft iron</button>
                            <button className={'cw-btn ' + (!soft ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setSoft(false)}>Steel</button>
                        </div>
                        <Flag kind="neutral">
                            Only some materials are magnetic — <b>iron, steel, nickel and cobalt</b>. <b>Soft iron</b> makes a
                            <b> temporary</b> magnet: it magnetises strongly but <b>loses</b> it as soon as the magnet is removed
                            (good for electromagnets). <b>Steel</b> makes a <b>permanent</b> magnet: harder to magnetise, but it
                            <b> keeps</b> its magnetism.
                        </Flag>
                    </>
                )}

                {mode === 'field' && (
                    <>
                        <Stat label="magnetic field of a bar magnet" value="N → S, strongest at the poles" tone="acc"
                              sub={<>a <b>magnetic field</b> is a region where a magnetic pole feels a force; the <b>arrows</b> show the direction of the force on a <b>north</b> pole, and the lines <b>crowd where the field is strongest</b> (at the poles).</>} />
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (view === 'lines' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setView('lines')}>Field lines</button>
                            <button className={'cw-btn ' + (view === 'filings' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setView('filings')}>Iron filings</button>
                            <button className={'cw-btn ' + (view === 'compass' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setView('compass')}>Plotting compass</button>
                        </div>
                        <Flag kind="neutral">
                            A <b>magnetic field</b> is a region in which a magnetic pole experiences a force. You plot it with <b>iron
                            filings</b> (which line up along the field) or a <b>plotting compass</b> (whose needle points along the
                            field). The <b>field lines</b> run from <b>N to S</b> outside the magnet; their <b>direction</b> is the force
                            on a north pole, and where they are <b>closer together the field is stronger</b>.
                        </Flag>
                    </>
                )}

                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, flipped, view, soft })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
